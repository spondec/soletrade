<?php

namespace App\Trade;

use App\Exceptions\PositionExitFailed;
use App\Models\Runner;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Contracts\Exchange\HasLeverage;
use App\Trade\Evaluation\Position;
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

        if ($lastTrade && $lastTrade->price_date > $this->runner->start_date)
        {
            if (!$this->loop)
            {
                $this->initNewLoop($lastTrade);
            }
            else if ($lastTrade->id == $trades->getNextTrade($this->loop->entry)?->id)
            {
                if (!$this->loop->status()->isEntered())
                {
                    $this->endLoop();
                    $this->initNewLoop($lastTrade);
                }
                else if ($this->loop->entry->isBuy() != $lastTrade->isBuy())
                {
                    $this->loop->setExitTrade($lastTrade);
                }
            }

            $this?->loop->run();
        }

        return $this?->loop?->status();
    }

    protected function initNewLoop(TradeSetup $trade): void
    {
        Log::info('New trade loop initiated.');
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

    protected function endLoop(): void
    {
        if (!$loop = $this->loop)
        {
            return;
        }
        Log::info('Ending loop.');

        $this->loop = null;
        $loop->order->cancelAll();
        $pos = $loop->status()->getPosition();

        if ($pos && $pos->isOpen())
        {
            Log::info('Force stopping position.');
            $pos->stop(time());

            RecoverableRequest::new(static function () use ($pos, $loop) {

                $loop->order->syncAll();
                if ($pos->isOpen())
                {
                    throw new PositionExitFailed('Failed to stop position.');
                }

            }, handle: [PositionExitFailed::class])->run();
        }
    }

    protected function onShutdown(): void
    {
        static $called = false;

        if (!$called)
        {
            $called = true;
//            \DB::unprepared('UNLOCK TABLES');
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

    protected function onPositionExit(Position $position): void
    {
        $this->evaluate($position);
        $this->endLoop();
        $this->setStatus(Status::AWAITING_TRADE);
    }

    protected function evaluate(Position $position): void
    {
        if ($position->isOpen())
        {
            throw new \LogicException('Can not evaluate an open position.');
        }

        //TODO:: register fill commissions
        $this->tradeAsset->registerRoi($position->relativeExitRoi());
    }
}
