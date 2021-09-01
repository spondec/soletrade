<?php

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use Illuminate\Support\Facades\App;

class Evaluator
{
    protected SymbolRepository $symbolRepo;

    public function __construct()
    {
        $this->symbolRepo = App::make(SymbolRepository::class);
    }

    protected function setup(TradeSetup|Signal $entry, TradeSetup|Signal $exit): Evaluation
    {
        $evaluation = new Evaluation();

        $evaluation->entry()->associate($entry);
        $evaluation->exit()->associate($exit);

        $this->assertEntryExitTime($evaluation);

        return $evaluation;
    }

    protected function assertEntryExitTime(Evaluation $evaluation): void
    {
        if ($evaluation->exit->timestamp <= $evaluation->entry->timestamp)
        {
            throw new \LogicException('Exit date must not be newer than or equal to entry trade.');
        }
    }

    protected function assertExitSignal(Evaluation $evaluation)
    {
        if (!$evaluation->exit)
        {
            throw new \InvalidArgumentException('Exit signal/setup does not exist.');
        }
    }

    protected function realizeTrade(Evaluation $evaluation): void
    {
        $this->assertExitSignal($evaluation);

        $candle = $this->symbolRepo->fetchLowestHighestPriceBetween($evaluation->entry->symbol,
            $evaluation->entry->timestamp,
            $evaluation->exit->timestamp);

        $evaluation->highest_price = $candle->h;
        $evaluation->lowest_price = $candle->l;

        $candles = $this->symbolRepo->fetchCandlesBetween($evaluation->entry->symbol,
            $evaluation->entry->timestamp,
            $evaluation->exit->timestamp, '1m'); //fetch 1m candles to minimize the ambiguity

        $lowestEntry = INF;
        $highestEntry = 0;
        $realEntryTime = null;

        $entryPrice = $evaluation->entry->price;
        $stopPrice = $evaluation->entry->stop_price;
        $closePrice = $evaluation->entry->close_price;

        $entered = $stopped = $ambiguous = $closed = false;
        foreach ($candles as $candle)
        {
            $low = $candle->l;
            $high = $candle->h;

            if (!$realEntryTime)
            {
                if ($low < $lowestEntry)
                {
                    $lowestEntry = $low;
                }
                if ($high > $highestEntry)
                {
                    $highestEntry = $high;
                }
                if ($this->isPriceInRange($entryPrice, $high, $low))
                {
                    $entered = true;
                    $realEntryTime = (int)$candle->t;
                    $evaluation->highest_entry_price = (float)$highestEntry;
                    $evaluation->lowest_entry_price = (float)$lowestEntry;
                    $evaluation->entry_timestamp = $realEntryTime;
                }
            }

            if ($entered)
            {
                if ($stopPrice && $this->isPriceInRange($stopPrice, $high, $low))
                {
                    $stopped = true;
                }
                if ($closePrice && $this->isPriceInRange($closePrice, $high, $low))
                {
                    $closed = true;

                    if ($stopped)
                    {
                        $ambiguous = true;
                    }
                }
                if ($stopped || $closed)
                {
                    $evaluation->exit_timestamp = $candle->t;
                    break;
                }
            }
        }

        $evaluation->is_stopped = $stopped;
        $evaluation->is_closed = $closed;
        $evaluation->is_ambiguous = $ambiguous;
        $evaluation->is_entry_price_valid = $entered;

        $this->calcHighLowRealRoi($evaluation);
    }

    public function isPriceInRange(float $price, float $high, float $low): bool
    {
        return $price <= $high && $price >= $low;
    }

    public function evaluate(TradeSetup|Signal $entry, TradeSetup|Signal $exit): Evaluation
    {
        $evaluation = $this->setup($entry, $exit);

        /** @var Evaluation|null $evaluation */
        if ($exists = $evaluation->findUnique(['entry', 'exit']))
        {
            return $exists;
        }

        $this->realizeTrade($evaluation);

        $evaluation->save();

        return $evaluation;
    }

    public function calcRoi(string $side, int|float $entryPrice, int|float $exitPrice): float
    {
        $roi = ($exitPrice - $entryPrice) * 100 / $entryPrice;

        if ($side === Signal::SELL)
        {
            $roi *= -1;
        }

        return round($roi, 2);
    }

    protected function calcHighLowRealRoi(Evaluation $evaluation): void
    {
        if (!$evaluation->is_entry_price_valid || $evaluation->is_ambiguous)
        {
            return;
        }

        $side = $evaluation->entry->side;
        $entryPrice = $evaluation->entry->price;
        $buy = $evaluation->entry->side === Signal::BUY;

        $exitPrice = $evaluation->getExitPrice();

        $evaluation->realized_roi = $this->calcRoi($side, $entryPrice, $exitPrice);
        $evaluation->highest_roi = $this->calcRoi($side, $entryPrice,
            $buy ? $evaluation->highest_price : $evaluation->lowest_price);
        $evaluation->lowest_roi = $this->calcRoi($side, $entryPrice,
            !$buy ? $evaluation->highest_price : $evaluation->lowest_price);

        //TODO ROIs of after the entry until exit
    }
}