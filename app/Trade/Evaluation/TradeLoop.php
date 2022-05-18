<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use App\Trade\HasConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\Pure;

class TradeLoop
{
    use HasConfig;

    protected array $config = [];

    protected SymbolRepository $repo;
    protected int $startDate;
    protected ?int $timeoutDate = null;
    protected ?int $lastRunDate = null;
    protected Collection $candles;
    protected \stdClass $firstCandle;
    protected TradeStatus $status;
    protected ?int $timeout;
    public readonly TradeSetup $exit;
    /**
     * Exit run sets the exit price of the trade and tries to exit once.
     *
     * @var bool
     */
    protected readonly bool $isExitRunCompleted;

    public function __construct(public           readonly TradeSetup $entry,
                                protected Symbol $evaluationSymbol,
                                array            $config)
    {
        $this->mergeConfig($config);
        $this->assertTradeSymbolMatchesEvaluationSymbol();
        $this->initTradeStatus();
        $this->repo = App::make(SymbolRepository::class);

        $this->firstCandle = $this->getFirstCandle($this->entry);
        $this->startDate = $this->firstCandle->t;

        $this->timeout = $this->config('timeout');

        if (!$this->repo->fetchCandle($this->evaluationSymbol, $this->entry->timestamp))
        {
            throw new \InvalidArgumentException('Evaluation interval candles are not complete.');
        }
    }

    protected function assertTradeSymbolMatchesEvaluationSymbol(): void
    {
        if ($this->entry->symbol->symbol !== $this->evaluationSymbol->symbol)
        {
            throw new \InvalidArgumentException('Evaluation symbol name does not match with the TradeSetup symbol name.');
        }
    }

    protected function initTradeStatus(): void
    {
        $this->status = new TradeStatus($this->entry);
        $this->registerTradeStatusListeners();
    }

    protected function registerTradeStatusListeners(): void
    {
        $this->status->listen('positionEntry', $this->onPositionEntry(...));
    }

    protected function getFirstCandle(TradeSetup $setup): \stdClass
    {
        return $this->repo->fetchNextCandle($this->evaluationSymbol, $setup->price_date) //candle is closed
            ?? DB::table('candles')
                ->where('symbol_id', $this->evaluationSymbol->id)
                ->where('t', '>=', $setup->timestamp)
                ->where('t', '<=', $setup->price_date)
                ->orderBy('t', 'DESC')
                ->first(); //not closed
    }

    public function __destruct()
    {
        if ($this->status->getPosition() && !$this->status->isExited())
        {
            throw new \LogicException('TradeLoop can not be destroyed before the trade is exited');
        }
    }

    public function setExitTrade(TradeSetup $exit): void
    {
        $this->assertExitDateGreaterThanEntryDate($this->entry->price_date, $exit->price_date);
        $this->exit = $exit;
    }

    public function isClosesOnExit(): bool
    {
        return $this->config('closeOnExit');
    }

    public function hasExitTrade(): bool
    {
        return isset($this->exit);
    }

    protected function assertExitDateGreaterThanEntryDate(int $startDate, int $endDate): void
    {
        if ($endDate <= $startDate)
        {
            throw new \LogicException('End date must not be newer than or equal to start date.');
        }
    }

    public function run(): TradeStatus
    {
        $candles = null;
        if ($this->hasExitTrade() && !isset($this->isExitRunCompleted))
        {
            $this->isExitRunCompleted = true;
            $lastCandle = $this->repo->fetchNextCandle($this->evaluationSymbol, $this->exit->price_date);
            if ($lastCandle)
            {
                $candles = $this->getCandlesBetween($lastCandle->t);
            }
        }

        if ($candles)
        {
            $this->runLoop($candles);
        }
        else
        {
            $this->runToEnd();
        }

        $this->performPostRunChecks();

        return $this->status;
    }

    protected function getCandlesBetween(int $endDate): Collection
    {
        if ($this->lastRunDate)
        {
            return $this->repo->assertCandlesBetween($this->evaluationSymbol,
                $this->lastRunDate,
                $endDate);
        }
        return $this->repo->assertCandlesBetween($this->evaluationSymbol,
            $this->firstCandle->t,
            $endDate,
            includeStart: true);
    }

    protected function runLoop(Collection $candles): void
    {
        $this->assertPreLoopRequisites($candles);

        $iterator = $candles->getIterator();

        $entry = $this->status->getEntryPrice();
        $exit = $this->status->getTargetPrice();
        $stop = $this->status->getStopPrice();

        while ($iterator->valid())
        {
            $candle = $nextCandle ?? $iterator->current();
            $key = $iterator->key();
            $iterator->next();
            $nextCandle = $iterator->current();

            $candle->l = (float)$candle->l;
            $candle->h = (float)$candle->h;
            $candle->t = (int)$candle->t;

            if (!$this->status->isEntered())
            {
                $this->loadBinding($entry, 'price', $candle);
                $priceDate = $this->getPriceDate($candle, $nextCandle);
                $this->tryPositionEntry($candle, $priceDate);
            }
            else if (!$this->status->isExited())
            {
                if ($stop)
                {
                    $this->loadBinding($stop, 'stop_price', $candle);
                }
                if ($exit)
                {
                    $this->loadBinding($exit, 'target_price', $candle);
                }

                $priceDate = $this->getPriceDate($candle, $nextCandle);
                $position = $position ?? $this->getPosition();

                if ($this->timeout && $this->hasPositionTimedOut($priceDate))
                {
                    $this->stopPositionAtClosePrice($position, $candle, 'Trade timed out. Stopping.');
                    break;
                }

                $this->tryPositionExit($position, $candle, $priceDate);
                $this->status->runTradeActions($candle, $priceDate);
            }
        }

        $this->lastRunDate = $this->isLastCandle($candle)
            ? $candles[$key - 1]?->t
            ?? $this->getPrevCandle($candle)->t
            : $candle->t;
    }

    protected function getPriceDate(\stdClass $candle, ?\stdClass $next): int
    {
        return $this->repo->getPriceDate($candle->t, $next?->t, $this->evaluationSymbol);
    }

    protected function tryPositionEntry(\stdClass $candle, int $priceDate): void
    {
        if (Calc::inRange($this->status->getEntryPrice()->get(), $candle->h, $candle->l))
        {
            $this->status->enterPosition($priceDate);
        }
    }

    #[Pure] protected function getPosition(): ?Position
    {
        return $this->status->getPosition();
    }

    #[Pure] protected function hasPositionTimedOut(int $priceDate): bool
    {
        return $this->timeoutDate <= $priceDate;
    }

    protected function stopPositionAtClosePrice(Position $position, \stdClass $candle, string $reason): void
    {
        if ($this->status->isAmbiguous())
        {
            return;
        }

        $priceDate = $this->getPriceDate($candle, null);

        if ($stop = $position->price('stop'))
        {
            $stop->set((float)$candle->c, $priceDate, $reason, true);
        }
        else
        {
            $position->addStopPrice($stop = new Price((float)$candle->c, $priceDate));
            $stop->newLog($priceDate, $reason, true);
        }

        $position->stop($priceDate);
    }

    protected function tryPositionExit(Position $position, \stdClass $candle, int $priceDate): void
    {
        if ($this->status->isExited())
        {
            return;
        }

        $stopped = $this->status->checkIsStopped($candle);
        $closed = $this->status->checkIsClosed($candle);

        if ((!$stopped && !$closed) || $this->status->isAmbiguous())
        {
            return;
        }

        if ($stopped)
        {
            $position->stop($priceDate);
        }
        if ($closed)
        {
            $position->close($priceDate);
        }
    }

    protected function isLastCandle(\stdClass $candle): bool //TODO:: needs caching
    {
        return !(bool)$this->repo->fetchNextCandle($candle->symbol_id, $candle->t);
    }

    protected function getPrevCandle(\stdClass $candle): \stdClass
    {
        return $this->repo->findCandles($this->evaluationSymbol)
            ->where('t', '<', $candle->t)
            ->orderBy('t', 'DESC')
            ->first();
    }

    protected function runToEnd(int $chunk = 10000): void
    {
        while (($candles = $this->getCandlesLimit($chunk))->first())
        {
            $this->runLoop($candles);

            if (!isset($candles[1])) //prevent infinite loop on the last candle
            {
                break;
            }
        }
    }

    protected function getCandlesLimit(int $limit): Collection
    {
        if ($this->lastRunDate)
        {
            return $this->repo->assertCandlesLimit($this->evaluationSymbol, $this->lastRunDate, limit: $limit);
        }
        return $this->repo->assertCandlesLimit($this->evaluationSymbol, $this->firstCandle->t, limit: $limit, includeStart: true);
    }

    protected function performPostRunChecks(): void
    {
        $position = $this->getPosition();

        if ($position && $position->isOpen())
        {
            if ($this->hasExitTrade() && $this->isClosesOnExit())
            {
                $candle = $this->getLastCandle();
                $priceDate = $this->getPriceDate($candle, null);

                $this->overrideTargetPrice($position, $candle, $priceDate);
                $this->tryPositionExit($position, $candle, $priceDate);
            }

            $this->continue($this->timeoutDate);
        }
    }

    protected function getLastCandle(): \stdClass
    {
        $candle = $this->repo->fetchCandle($this->evaluationSymbol, $this->lastRunDate);

        if (!$candle)
        {
            throw new \LogicException('No candle found for last run date.');
        }

        $candle->h = (float)$candle->h;
        $candle->l = (float)$candle->l;
        $candle->c = (float)$candle->c;
        $candle->o = (float)$candle->o;

        return $candle;
    }

    protected function continue(?int $endDate): void
    {
        if (!$endDate)
        {
            $this->runToEnd();
            return;
        }

        $this->assertExitDateGreaterThanEntryDate($this->lastRunDate, $endDate);
        $candles = $this->getCandlesBetween($endDate);

        $this->runLoop($candles);
    }

    public function status(): TradeStatus
    {
        return $this->status;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'closeOnExit' => true,
            'timeout'     => 0
        ];
    }

    protected function onPositionEntry(): void
    {
        if (!$this->timeoutDate && $this->timeout && $position = $this->getPosition())
        {
            $this->timeoutDate = $position->entryTime() + $this->timeout * 60 * 1000;
        }
    }

    protected function assertPreLoopRequisites(Collection $candles): void
    {
        if (!$first = $candles->first())
        {
            throw new \LogicException('Can not loop through an empty set.');
        }

        if ($first->symbol_id != $this->evaluationSymbol->id)
        {
            throw new \InvalidArgumentException('Invalid candles provided.');
        }
    }

    protected function overrideTargetPrice(Position $position, \stdClass $candle, int $priceDate): void
    {
        if ($target = $position->price('exit'))
        {
            $target->set((float)$candle->c, $priceDate, 'Target price overridden.', true);
        }
        else
        {
            $this->status->setExitPrice((float)$candle->c, $priceDate);
        }
    }

    protected function loadBinding(Price $price, string $column, \stdClass $candle): void
    {
        $this->entry->loadBindingPrice($price, $column, $candle->t);
    }
}