<?php

namespace App\Trade\Evaluation;

use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class TradeLoop
{
    protected SymbolRepository $repo;

    protected int $startDate;
    protected int $lastRunDate;

    protected Collection $candles;

    protected \stdClass $firstCandle;
    protected float $entryPrice;
    protected float $stopPrice;

    protected float $closePrice;

    protected TradeStatus $status;

    public function __construct(protected TradeSetup $entry, protected Symbol $evaluationSymbol)
    {
        $this->assertTradeSymbolMatchesEvaluationSymbol();
        $this->status = new TradeStatus($entry);
        $this->repo = App::make(SymbolRepository::class);

        $this->firstCandle = $this->repo->assertNextCandle($entry->symbol_id, $entry->price_date);
        $this->startDate = $this->firstCandle->t;
    }

    protected function assertTradeSymbolMatchesEvaluationSymbol(): void
    {
        if ($this->entry->symbol->symbol !== $this->evaluationSymbol->symbol)
        {
            throw new \InvalidArgumentException('Evaluation symbol name does not match with the TradeSetup symbol name.');
        }
    }

    public function getLastCandle(): \stdClass
    {
        return $this->repo->fetchCandle($this->entry->symbol, $this->lastRunDate, $this->evaluationSymbol->interval);
    }

    public function getLastRunDate(): int
    {
        return $this->lastRunDate;
    }

    public function runToExit(TradeSetup $exit): ?Position
    {
        $this->assertExitDateGreaterThanEntryDate($this->entry->price_date, $exit->price_date);

        $lastCandle = $this->repo->assertNextCandle($this->entry->symbol_id, $exit->price_date);
        $candles = $this->repo->assertCandlesBetween($this->entry->symbol,
            $this->firstCandle->t,
            $lastCandle->t,
            $this->evaluationSymbol->interval);
        $savePoint = $this->newSavePointAccess($this->firstCandle->t, $lastCandle->t);

        $this->runLoop($candles, $savePoint);

        return $this->status->getPosition();
    }

    protected function assertExitDateGreaterThanEntryDate(int $startDate, int $endDate): void
    {
        if ($endDate <= $startDate)
        {
            throw new \LogicException('End date must not be newer than or equal to start date.');
        }
    }

    protected function newSavePointAccess(int $startDate, int $endDate): SavePointAccess
    {
        return new SavePointAccess($this->entry, $startDate, $endDate);
    }

    protected function runLoop(Collection $candles, SavePointAccess $savePoint): void
    {
        if (!$candles->first())
        {
            throw new \LogicException('Can not loop through an empty set.');
        }

        $iterator = $candles->getIterator();

        while ($iterator->valid())
        {
            $candle = $iterator->current();
            $iterator->next();
            $nextCandle = $iterator->current();

            $candle->l = (float)$candle->l;
            $candle->h = (float)$candle->h;
            $candle->t = (int)$candle->t;

            if (!$this->status->isEntered())
            {
                $this->loadSavePointPrice($this->status->getEntryPrice(),
                    $savePoint,
                    'price',
                    $candle->t);
                $this->status->updateLowestHighestEntryPrice($candle);
                $this->tryPositionEntry($candle, $nextCandle);
            }
            else
            {
                $this->status->logRiskReward($candle);

                if (!$this->status->isExited())
                {
                    $this->loadSavePointPrice($this->status->getStopPrice(),
                        $savePoint,
                        'stop_price',
                        $candle->t);
                    $this->loadSavePointPrice($this->status->getClosePrice(),
                        $savePoint,
                        'close_price',
                        $candle->t);
                    $this->tryPositionExit($this->status->getPosition(),
                        $candle, $nextCandle);
                }
            }
        }

        $this->lastRunDate = $candle->t;
        $pivots = $this->fetchPivotsFromStartToLastRun();
        $this->status->updateHighestLowestPrice($pivots['highest'], $pivots['lowest']);
    }

    protected function loadSavePointPrice(Price $price, SavePointAccess $savePoint, string $column, int $timestamp): void
    {
        if (!$price->isLocked() && $entryPrice = $savePoint->lastPoint($column, $timestamp))
        {
            $price->set($entryPrice, 'SavePointAccess');
        }
    }

    protected function getPriceDate(\stdClass $candle, ?\stdClass $next): int
    {
        if ($next)
        {
            return $next->t - 1000;
        }

        if ($nextCandle = $this->repo->assertNextCandle($this->evaluationSymbol->id, $candle->t))
        {
            return $nextCandle->t - 1000;
        }

        return $this->evaluationSymbol->last_update;
    }

    protected function tryPositionEntry(\stdClass $candle, ?\stdClass $nextCandle): void
    {
        if (Calc::inRange($this->status->getEntryPrice()->get(), $candle->h, $candle->l))
        {
            $this->status->enterPosition($this->getPriceDate($candle, $nextCandle));
        }
    }

    protected function tryPositionExit(Position $position, \stdClass $candle, ?\stdClass $nextCandle): void
    {
        if (!$this->status->checkIsExited())
        {
            $stopped = $this->status->checkIsStopped($candle);
            $closed = $this->status->checkIsClosed($candle);

            if ($stopped || $closed)
            {
                if (!$this->status->isAmbiguous())
                {
                    if ($stopped)
                    {
                        $position->stop($priceDate = $this->getPriceDate($candle, $nextCandle));
                    }
                    if ($closed)
                    {
                        $position->close($priceDate ?? $this->getPriceDate($candle, $nextCandle));
                    }
                }
            }
            else
            {
                $this->status->runTradeActions($candle, $this->getPriceDate($candle, $nextCandle));
            }
        }
    }

    /**
     * @return \stdClass[]
     */
    protected function fetchPivotsFromStartToLastRun(): array
    {
        return $this->repo->assertLowestHighestCandle($this->entry->symbol_id, $this->startDate, $this->lastRunDate);
    }

    public function continue(int $endDate): ?Position
    {
        $this->assertExitDateGreaterThanEntryDate($this->lastRunDate, $endDate);
        $symbol = $this->entry->symbol;
        $intervalId = $this->repo->findSymbolIdForInterval($symbol, $this->evaluationSymbol->interval);
        $startDate = $this->repo->assertNextCandle($intervalId, $this->lastRunDate)->t;
        $candles = $this->repo->assertCandlesBetween($symbol,
            $startDate,
            $endDate,
            $this->evaluationSymbol->interval);
        $savePoint = $this->newSavePointAccess($this->lastRunDate, $endDate);

        $this->runLoop($candles, $savePoint);
        return $this->status->getPosition();
    }

    public function run(int $chunk = 100): ?Position
    {
        while (true)
        {
            $candles = $this->repo->assertCandlesLimit($this->entry->symbol,
                $startDate ?? $this->firstCandle->t,
                $chunk,
                $this->evaluationSymbol->interval);

            if (!$first = $candles->first())
            {
                break;
            }

            $savePoint = $this->newSavePointAccess($first->t, $startDate = $candles->last()->t);
            $this->runLoop($candles, $savePoint);
        }

        return $this->status->getPosition();
    }

    public function status(): TradeStatus
    {
        return $this->status;
    }

    public function updateCandles(): void
    {
        $this->evaluationSymbol->exchange()->updater()->update($this->evaluationSymbol);
    }

    public function getEvaluationSymbol(): Symbol
    {
        return $this->evaluationSymbol;
    }
}