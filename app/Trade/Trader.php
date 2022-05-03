<?php

namespace App\Trade;

use App\Exceptions\PositionExitFailed;
use App\Models\Runner;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Collection\TradeCollection;
use App\Trade\Contract\Exchange\HasLeverage;
use App\Trade\Evaluation\LivePosition;
use App\Trade\Evaluation\LiveTradeLoop;
use App\Trade\Evaluation\Price;
use App\Trade\Evaluation\TradeStatus;
use App\Trade\Exchange\Exchange;
use App\Trade\Process\RecoverableRequest;
use App\Trade\Strategy\Strategy;
use App\Trade\Enum\TraderStatus;

class Trader
{
    protected ?LiveTradeLoop $loop = null;

    protected TraderStatus $status = TraderStatus::STOPPED;
    protected Runner $runner;
    protected TradeCollection $trades;
    private bool $isEndingLoop = false;

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

    public function run(): ?TradeStatus
    {
        if ($this->status === TraderStatus::STOPPED)
        {
            return null;
        }

        $trades = $this->strategy->run($this->symbol);

        if (!isset($this->trades))
        {
            $this->trades = $trades;
        }
        else
        {
            $this->trades->mergeTrades($trades);
        }

        /** @var TradeSetup|null $lastTrade */
        $lastTrade = $this->trades->last();

        Log::info(fn() => "Total trades: {$trades->count()}");
        Log::info(fn() => "Cached trades: {$this->trades->count()}");
        Log::info(fn() => "First trade: {$this->trades->first()->id}", $this->trades->first());
        Log::info(fn() => "Last trade #{$lastTrade->id}", $lastTrade);

        if ($lastTrade && as_ms($lastTrade->price_date) > as_ms($this->runner->start_date))
        {
            if (!$this->loop)
            {
                $this->initNewLoop($lastTrade);
            }
            else if ($lastTrade->id == $this->trades->getNextTrade($this->loop->entry)?->id)
            {
                Log::info(fn() => 'New trade detected. #' . $lastTrade->id);
                if (!$this->loop->status()->isEntered())
                {
                    Log::info(fn() => "Entry failed, ending loop. #{$lastTrade->id}");
                    $this->endLoop();
                    $this->initNewLoop($lastTrade);
                }
                else if (!$this->loop->hasExitTrade() && $this->loop->entry->isBuy() != $lastTrade->isBuy())
                {
                    Log::info(fn() => "Setting exit trade #{$lastTrade->id}");
                    $this->loop->setExitTrade($lastTrade);
                }
            }
        }

        Log::info("Running loop...", $this->loop);
        $this->loop?->run();

        return $this->loop?->status();
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
        $this->setStatus(TraderStatus::AWAITING_ENTRY);

        $status->listen('positionEntry', function (TradeStatus $status) {
            /** @noinspection NullPointerExceptionInspection */
            $status->getPosition()->listen('exit', $this->onPositionExit(...));
            $this->setStatus(TraderStatus::IN_POSITION);
        });

        $this->trades->cleanUpBefore($trade);
    }

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

            if (!$position->price('stop'))
            {
                $lastCandle = $this->symbol->lastCandle();
                $position->addStopPrice(new Price($lastCandle->c, millitime()));
            }
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

    public function getStatus(): TraderStatus
    {
        return $this->status;
    }

    public function setStatus(TraderStatus $status): void
    {
        $this->status = $status;

        if ($this->status === TraderStatus::STOPPED)
        {
            $this->endLoop();
        }
    }

    public function keepAlive(): void
    {
        $this->runner->lengthenExpiry(600)->save();
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
        Log::info(fn() => "Position exited. Evaluating...");
        $this->evaluate($position);
        $this->endLoop();
        $this->setStatus(TraderStatus::AWAITING_TRADE);
    }

    protected function evaluate(LivePosition $position): void
    {
        if ($position->isOpen())
        {
            throw new \LogicException('Can not evaluate an open position.');
        }

        \App\Models\Trade::from($this->loop);
        //TODO:: register fill commissions
        $this->tradeAsset->registerRoi($position->relativeExitRoi());
    }
}
