<?php

namespace App\Trade\Evaluation;

use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use App\Trade\HasConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

final class TradeLoop
{
    use HasConfig;

    protected array $config = [
        'stopAtExit' => true,
        'timeout'    => 1440
    ];

    protected SymbolRepository $repo;

    protected int $startDate;
    protected int $endDate;

    protected ?int $lastRunDate = null;

    protected Collection $candles;

    protected \stdClass $firstCandle;

    protected TradeStatus $status;

    public function __construct(protected TradeSetup $entry, protected Symbol $evaluationSymbol, array $config)
    {
        $this->mergeConfig($config);
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

    public function getLastRunDate(): int
    {
        return $this->lastRunDate;
    }

    public function runToExit(TradeSetup $exit): TradeStatus
    {
        $this->assertExitDateGreaterThanEntryDate($this->entry->price_date, $exit->price_date);

        $lastCandle = $this->repo->assertNextCandle($this->entry->symbol_id, $exit->price_date);
        $candles = $this->repo->assertCandlesBetween($this->entry->symbol,
            $startDate = $this->lastRunDate ?? $this->firstCandle->t,
            $lastCandle->t,
            $this->evaluationSymbol->interval);
        $savePoint = $this->newSavePointAccess($startDate, $lastCandle->t);

        $this->runLoop($candles, $savePoint);

        $position = $this->getPosition();

        if ($position && $position->isOpen())
        {
            if ($this->config('stopAtExit'))
            {
                $this->stopPositionAtClosePrice($position,
                    $this->getLastCandle(),
                    'Stopping at exit setup.');
            }

            if ($this->shouldLoopContinue($this->getPriceDate($candle = $this->getLastCandle(),
                $this->repo->fetchNextCandle($this->evaluationSymbol->id, $candle->t))))
            {
                $this->continue($this->endDate);
            }
        }

        return $this->status;
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
        $timeout = $this->getTimeout();

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
                    $this->tryPositionExit($position = $this->getPosition(),
                        $candle, $nextCandle);

                    if ($timeout && $this->hasPositionTimedOut($position, $this->getPriceDate($candle, $nextCandle)))
                    {
                        $this->stopPositionAtClosePrice($position,
                            $candle,
                            'Trade timed out. Stopping.');
                    }

                    //TODO break at exit?
                }
            }
        }

        $this->lastRunDate = $candle->t;
        $pivots = $this->fetchPivotsFromStartToLastRun();
        $this->status->updateHighestLowestPrice($pivots['highest'], $pivots['lowest']);
    }

    protected function getTimeout(): bool|int
    {
        if (($timeout = $this->config('timeout')) > 0)
        {
            return $timeout;
        }

        return false;
    }

    protected function loadSavePointPrice(Price $price, SavePointAccess $savePoint, string $column, int $timestamp): void
    {
        if (!$price->isLocked() && $entryPrice = $savePoint->lastPoint($column, $timestamp))
        {
            $price->set($entryPrice, $timestamp, 'SavePoint: ' . $savePoint->getBindingName($column));
        }
    }

    protected function tryPositionEntry(\stdClass $candle, ?\stdClass $nextCandle): void
    {
        if (Calc::inRange($this->status->getEntryPrice()->get(), $candle->h, $candle->l))
        {
            $this->status->enterPosition($this->getPriceDate($candle, $nextCandle));

            if (($position = $this->getPosition()) && $timeout = $this->getTimeout())
            {
                $this->endDate = $position->entryTime() + $timeout * 60 * 1000;
            }
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

    #[Pure] protected function getPosition(): ?Position
    {
        return $this->status->getPosition();
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

    #[Pure] protected function hasPositionTimedOut(Position $position, int $priceDate): bool
    {
        return $position->isOpen() && $this->endDate <= $priceDate;
    }

    protected function stopPositionAtClosePrice(Position $position, \stdClass $candle, string $reason): void
    {
        $position->price('stop')->set((float)$candle->c,
            $priceDate = $this->getPriceDate($candle, null),
            $reason,
            true);
        $position->stop($priceDate);
    }

    /**
     * @return \stdClass[]
     */
    #[ArrayShape(['lowest' => \stdClass::class, 'highest' => \stdClass::class])]
    protected function fetchPivotsFromStartToLastRun(): array
    {
        return $this->repo->assertLowestHighestCandle($this->entry->symbol_id, $this->startDate, $this->lastRunDate);
    }

    public function getLastCandle(): \stdClass
    {
        return $this->repo->fetchCandle($this->entry->symbol, $this->lastRunDate, $this->evaluationSymbol->interval);
    }

    protected function shouldLoopContinue(int $priceDate): bool
    {
        return $this->endDate > $priceDate;
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
        return $this->getPosition();
    }

    public function status(): TradeStatus
    {
        return $this->status;
    }
}