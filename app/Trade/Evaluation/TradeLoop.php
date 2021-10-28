<?php

namespace App\Trade\Evaluation;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use JetBrains\PhpStorm\Pure;

class TradeLoop
{
    protected SymbolRepository $repo;

    protected ?Position $position = null;

    protected string $interval = '1m';

    protected array $log = [];
    protected array $riskRewardHistory = [];

    protected Price $entryPrice;
    protected Price $closePrice;
    protected Price $stopPrice;

    protected int $startDate;
    protected int $lastRunDate;

    protected ?float $lowestEntryPrice = null;
    protected ?float $highestEntryPrice = null;

    protected ?float $lowestPrice = null;
    protected ?float $highestPrice = null;

    protected bool $isBuy;
    protected bool $isAmbiguous = false;

    protected bool $entered = false;
    protected bool $exited = false;
    protected bool $closed = false;
    protected bool $stopped = false;

    protected Collection $candles;

    protected \stdClass $firstCandle;

    protected float $_entryPrice;
    protected float $_stopPrice;
    protected float $_closePrice;

    public function __construct(protected TradeSetup $entry)
    {
        $this->repo = App::make(SymbolRepository::class);
        $this->isBuy = $this->entry->side === Signal::BUY;
        $this->entryPrice = $this->newPrice($this->entry->price);
        $this->closePrice = $this->newPrice($this->entry->close_price);
        $this->stopPrice = $this->newPrice($this->entry->stop_price);
        $this->firstCandle = $this->repo->fetchNextCandle($this->entry->symbol_id, $this->entry->timestamp);
        $this->startDate = $this->firstCandle->t;
    }

    #[Pure] protected function newPrice(float $price, ?\Closure $onChange = null): Price
    {
        return new Price($price, $onChange);
    }

    public function getLastCandle(): \stdClass
    {
        return $this->repo->fetchCandle($this->entry->symbol,
            $this->getLastRunDate(),
            $this->getInterval());
    }

    public function getLastRunDate(): int
    {
        return $this->lastRunDate;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function runToExit(TradeSetup $exit): ?Position
    {
        $this->assertExitDateGreaterThanEntryDate($this->entry->timestamp, $exit->timestamp);

        $lastCandle = $this->repo->fetchNextCandle($this->entry->symbol_id, $exit->timestamp);
        $candles = $this->repo->fetchCandlesBetween($this->entry->symbol,
            $this->firstCandle->t,
            $lastCandle->t,
            $this->interval);
        $savePoint = $this->newSavePointAccess($this->firstCandle->t, $lastCandle->t);

        $this->runLoop($candles, $savePoint);

        return $this->position;
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
        foreach ($candles as $candle)
        {
            $candle->l = (float)$candle->l;
            $candle->h = (float)$candle->h;
            $candle->t = (int)$candle->t;

            if (!$this->entered)
            {
                $this->_entryPrice = $this->getSavePoint($this->entryPrice, $savePoint, 'price', $candle->t);
                $this->updateLowestHighestEntryPrice($candle);
                $this->tryPositionEntry($this->_entryPrice, $candle);
            }
            else
            {
                $this->logRiskReward($candle, $this->_entryPrice);

                if (!$this->exited)
                {
                    $this->_stopPrice = $this->getSavePoint($this->stopPrice, $savePoint, 'stop_price', $candle->t);
                    $this->_closePrice = $this->getSavePoint($this->closePrice, $savePoint, 'close_price', $candle->t);
                    $this->tryPositionExit($candle, $this->_stopPrice, $this->_closePrice);
                }
            }
        }

        $this->lastRunDate = $candle->t;
        $this->updateHighestLowestPrice();
    }

    protected function getSavePoint(Price $price, SavePointAccess $savePoint, string $column, int $timestamp): float
    {
        if ($price->isLocked())
        {
            $entryPrice = $price->get();
        }
        else
        {
            $entryPrice = $savePoint->lastPoint($column, $timestamp) ?? $price->get();
            $price->set($entryPrice, 'SavePointAccess');
        }

        return $entryPrice;
    }

    protected function updateLowestHighestEntryPrice(\stdClass $candle): void
    {
        if ($this->lowestEntryPrice === null || $candle->l < $this->lowestEntryPrice)
        {
            $this->lowestEntryPrice = $candle->l;
        }
        if ($this->highestEntryPrice === null || $candle->h > $this->highestEntryPrice)
        {
            $this->highestEntryPrice = $candle->h;
        }
    }

    protected function tryPositionEntry(float $entryPrice, \stdClass $candle): void
    {
        if (Calc::inRange($entryPrice, $candle->h, $candle->l))
        {
            $this->entryPrice->lock($this);
            $this->position = $this->newPosition($this->entry,
                $candle->t,
                $this->entryPrice,
                $this->closePrice,
                $this->stopPrice);
            $this->entered = true;
        }
    }

    protected function newPosition(TradeSetup $setup, int $entryTime, Price $entry, Price $exit, Price $stop): Position
    {
        return new Position($setup->isBuy(), $setup->size, $entryTime, $entry, $exit, $stop);
    }

    protected function logRiskReward(\stdClass $candle, float $entryPrice): void
    {
        if (!$this->lowestPrice)
        {
            $this->lowestPrice = $candle->l;
        }
        if (!$this->highestPrice)
        {
            $this->highestPrice = $candle->h;
        }

        $newLow = $candle->l < $this->lowestPrice;
        $newHigh = $candle->h > $this->highestPrice;

        if ($newLow || $newHigh)
        {
            if ($newLow)
            {
                $this->lowestPrice = $candle->l;
            }
            if ($newHigh)
            {
                $this->highestPrice = $candle->h;
            }

            $this->riskRewardHistory[$candle->t] = [
                'ratio'  => round(Calc::riskReward($this->isBuy,
                    $entryPrice,
                    $this->isBuy ? $this->highestPrice : $this->lowestPrice,
                    $this->isBuy ? $this->lowestPrice : $this->highestPrice,
                    $highRoi,
                    $lowRoi), 2),
                'reward' => $highRoi,
                'risk'   => $lowRoi
            ];
        }
    }

    protected function tryPositionExit(\stdClass $candle, float $stopPrice, float $closePrice): void
    {
        $this->stopped = Calc::inRange($stopPrice, $candle->h, $candle->l);
        $this->closed = Calc::inRange($closePrice, $candle->h, $candle->l);

        if ($this->stopped || $this->closed)
        {
            $this->exited = true;

            if ($this->stopped && $this->closed)
            {
                $this->isAmbiguous = true;
            }
            else
            {
                if ($this->stopped)
                {
                    $this->position->stop($candle->t);
                }
                if ($this->closed)
                {
                    $this->position->close($candle->t);
                }
            }
        }
        else
        {
            $this->runTradeActions($candle, $this->position);
        }
    }

    protected function runTradeActions(\stdClass $candle, Position $position): void
    {

    }

    protected function updateHighestLowestPrice(): void
    {
        $price = $this->repo->fetchLowestHighestCandle($this->entry->symbol_id, $this->startDate, $this->lastRunDate);

        $highest = $price['highest']->h;
        $lowest = $price['lowest']->l;

        if ($this->highestPrice === null || $highest > $this->highestPrice)
        {
            $this->highestPrice = $highest;
        }
        if ($this->lowestPrice === null || $lowest < $this->lowestPrice)
        {
            $this->lowestPrice = $lowest;
        }
    }

    public function continue(int $endDate): ?Position
    {
        $this->assertExitDateGreaterThanEntryDate($this->lastRunDate, $endDate);
        $symbol = $this->entry->symbol;
        $intervalId = $this->repo->findSymbolIdForInterval($symbol, $this->interval);
        $startDate = $this->repo->fetchNextCandle($intervalId, $this->lastRunDate)->t;
        $candles = $this->repo->fetchCandlesBetween($symbol,
            $startDate,
            $endDate,
            $this->interval);
        $savePoint = $this->newSavePointAccess($this->lastRunDate, $endDate);

        $this->runLoop($candles, $savePoint);
        return $this->position;
    }

    public function run(int $chunk = 100): ?Position
    {
        while (true)
        {
            $candles = $this->repo->fetchCandlesLimit($this->entry->symbol,
                $startDate ?? $this->firstCandle->t,
                $chunk,
                $this->interval);

            if (!$first = $candles->first())
            {
                break;
            }

            $savePoint = $this->newSavePointAccess($first->t, $startDate = $candles->last()->t);
            $this->runLoop($candles, $savePoint);
        }

        return $this->position;
    }

    public function riskRewardHistory(): array
    {
        return $this->riskRewardHistory;
    }

    public function isAmbiguous(): bool
    {
        return $this->isAmbiguous;
    }

    #[Pure] public function getEntryPrice(): float
    {
        return $this->entryPrice->get();
    }

    #[Pure] public function getClosePrice(): float
    {
        return $this->closePrice->get();
    }

    #[Pure] public function getStopPrice(): float
    {
        return $this->stopPrice->get();
    }

    public function getLowestPrice(): ?float
    {
        return $this->lowestPrice;
    }

    public function getHighestPrice(): ?float
    {
        return $this->highestPrice;
    }

    public function getLowestEntryPrice(): float
    {
        return $this->lowestEntryPrice;
    }

    public function getHighestEntryPrice(): float|int
    {
        return $this->highestEntryPrice;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function getEntry(): TradeSetup
    {
        return $this->entry;
    }
}