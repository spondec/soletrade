<?php

namespace App\Trade;

use App\Exceptions\PositionExitFailed;
use App\Models\Runner;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Contracts\Exchange\HasLeverage;
use App\Trade\Evaluation\LivePosition;
use App\Trade\Evaluation\TradeStatus;
use App\Trade\Exchange\Exchange;
use App\Trade\Process\RecoverableRequest;
use App\Trade\Strategy\Strategy;

enum Status: string
{
    case STOPPED = 'Stopped';
    case AWAITING_ENTRY = 'Awaiting Entry';
    case IN_POSITION = 'In Position';
    case AWAITING_TRADE = 'Awaiting Trade';
}

class Trader
{
    protected ?LiveTradeLoop $loop = null;

    protected Status $status = Status::STOPPED;
    protected Runner $runner;

    public function __construct(public readonly Strategy $strategy,
                                public readonly Exchange $exchange,
                                public readonly Symbol $symbol,
                                public readonly TradeAsset $tradeAsset)
    {
        Runner::purgeExpired();

        if (!\App::runningInConsole())
        {
            throw new \LogicException('Live trading is only available in CLI.');
        }

        set_time_limit(0);
        on_shutdown($this->onShutdown(...));

        if (Runner::query()->first())
        {
            throw new \LogicException('Multiple trade runners are not supported.');
        }

        $this->runner = new Runner;
        $this->runner->setExpiry(600)->save();
    }

    public function loop(): ?LiveTradeLoop
    {
        return $this->loop;
    }

    public function setLeverage(float $leverage): void
    {
        if (!$this->exchange instanceof HasLeverage)
        {
            throw new \LogicException("{$this->exchange::name()} does not support leverage.");
        }

        RecoverableRequest::new(
            fn() => $this->exchange->setLeverage($leverage, $this->symbol->symbol)
        )->run();
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): void
    {
        $this->status = $status;

        if ($this->status === Status::STOPPED)
        {
            $this->endLoop();
        }
    }

    public function keepAlive(): void
    {
        $this->runner->lengthenExpiry(600)->save();
    }

    public function run(): ?TradeStatus
    {
        if ($this->status === Status::STOPPED)
        {
            return null;
        }

        /** @var TradeSetup $lastTrade */
        $lastTrade = ($trades = $this->strategy->run($this->symbol))->last();
        Log::info(fn() => "Total trades: {$trades->count()}");

        if ($trades->first())
            Log::info(fn() => "First trade: {$trades->first()->id}");
        if ($lastTrade)
            Log::info(fn() => "Last trade #{$lastTrade->id}");

        if ($lastTrade && Calc::asMs($lastTrade->price_date) > Calc::asMs($this->runner->start_date))
        {
            if (!$this->loop)
            {
                $this->initNewLoop($lastTrade);
            }
            else if ($lastTrade->id == $trades->getNextTrade($this->loop->entry)?->id)
            {
                Log::info(fn() => 'New trade detected. #' . $lastTrade->id);
                if (!$this->loop->status()->isEntered())
                {
                    Log::info(fn() => "Entry failed, ending trade. #{$lastTrade->id}");
                    $this->endLoop();
                    $this->initNewLoop($lastTrade);
                }
                else if (!$this->loop->hasExitTrade() && $this->loop->entry->isBuy() != $lastTrade->isBuy())
                {
                    Log::info(fn() => "Setting exit trade #{$lastTrade->id}");
                    $this->loop->setExitTrade($lastTrade);
                }
            }

            Log::info("Running loop...");
            $this?->loop->run();
        }

        return $this?->loop?->status();
    }

    protected function initNewLoop(TradeSetup $trade): void
    {
        Log::info(fn() => "Initializing new loop. #{$trade->id}");
        $orderManager = new OrderManager($this->exchange,
            $this->symbol,
            $this->tradeAsset,
            $trade);

        $this->loop = new LiveTradeLoop($trade,
            $this->strategy->evaluationSymbol(),
            $this->strategy->config('evaluation.loop'),
            $orderManager);

        $status = $this->loop->status();
        $this->setStatus(Status::AWAITING_ENTRY);

        $status->listen('positionEntry', function (TradeStatus $status) {
            /** @noinspection NullPointerExceptionInspection */
            $status->getPosition()->listen('exit', $this->onPositionExit(...));
            $this->setStatus(Status::IN_POSITION);
        });
    }

    private bool $isEndingLoop = false;

    protected function endLoop(): void
    {
        if ($this->isEndingLoop || !$this->loop)
        {
            return;
        }

        $this->isEndingLoop = true;

        Log::info('Ending loop.');

        $this->loop->order->cancelAll();
        $position = $this->loop->status()->getPosition();

        if ($position && $position->isOpen())
        {
            Log::info('Force stopping position.');
            $position->stop(time());

            RecoverableRequest::new(function () use ($position) {

                $this->loop->order->syncAll();
                if ($position->isOpen())
                {
                    throw new PositionExitFailed('Failed to stop position.');
                }

            }, handle: [PositionExitFailed::class])->run();
        }

        $this->isEndingLoop = false;
        $this->loop = null;
    }

    protected function onShutdown(): void
    {
        static $called = false;

        if (!$called)
        {
            $called = true;
            try
            {
                $this->endLoop();
            } catch (\Throwable $e)
            {
                //on shutdown, the error won't get logged
                //so make sure to log it here
                Log::error($e);
                throw $e;
            } finally
            {
                $this->runner->delete();
            }
        }
    }

    protected function onPositionExit(LivePosition $position): void
    {
        $this->evaluate($position);
        $this->endLoop();
        $this->setStatus(Status::AWAITING_TRADE);
    }

    protected function evaluate(LivePosition $position): void
    {
        if ($position->isOpen())
        {
            throw new \LogicException('Can not evaluate an open position.');
        }

        \App\Models\Position::from($this->loop);
        //TODO:: register fill commissions
        $this->tradeAsset->registerRoi($position->relativeExitRoi());
    }
}
