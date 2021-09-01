<?php

namespace App\Trade;

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

    protected function setupEvaluation(TradeSetup|Signal $entry, TradeSetup|Signal $exit): Evaluation
    {
        $evaluation = new Evaluation();

        $evaluation->entry()->associate($entry);
        $evaluation->exit()->associate($exit);
        $evaluation->side = $entry->side;

        $this->assertEntryExitTime($evaluation);

        return $evaluation;
    }

    protected function assertEntryExitTime(Evaluation $evaluation): void
    {
        if ($evaluation->exit->timestamp <= $evaluation->entry->timestamp)
        {
            throw new \LogicException('Exit trade must not be newer than entry trade.');
        }
    }

    public function validateEntryPrice(Evaluation $evaluation): bool
    {
        $entryPrice = $evaluation->entry->price;

        $candle = $this->symbolRepo->fetchLowestHighestPriceBetween($evaluation->entry->symbol_id,
            $evaluation->entry->timestamp,
            $evaluation->exit->timestamp);

        $evaluation->highest_price = $candle->h;
        $evaluation->lowest_price = $candle->l;

        $evaluation->is_entry_price_valid = $isValid = ($entryPrice >= $candle->l && $entryPrice <= $candle->h);

        return $isValid;
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

        $candles = $this->symbolRepo->fetchCandlesBetween($evaluation->entry->symbol,
            $evaluation->entry->timestamp,
            $evaluation->exit->timestamp);

        $lowestEntry = INF;
        $highestEntry = 0;
        $realEntryTime = null;

        $entryPrice = $evaluation->entry->price;
        $stopPrice = $evaluation->entry->stop_price;
        $closePrice = $evaluation->entry->close_price;

        $break = false;
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
                if ($entryPrice >= $low && $entryPrice <= $high)
                {
                    $realEntryTime = (float)$candle->t;
                    $evaluation->highest_entry_price = (float)$highestEntry;
                    $evaluation->lowest_entry_price = (float)$lowestEntry;
                    $evaluation->entry_timestamp = $realEntryTime;
                }
            }

            if ($stopPrice && $this->isPriceInRange($stopPrice, $high, $low))
            {
                $isStopped = true;
                $break = true;
            }

            //TODO handle take profits

            if ($closePrice && $this->isPriceInRange($closePrice, $high, $low))
            {
                $isClosed = true;

                if ($break)
                {
                    $isAmbiguous = true;
                }

                $break = true;
            }

            if ($break ?? false)
            {
                $evaluation->setExitTime($candle->t);
                break;
            }
        }

        $evaluation->is_stopped = $isStopped ?? false;
        $evaluation->is_closed = $isClosed ?? false;
        $evaluation->is_ambiguous = $isAmbiguous ?? false;

        $this->calcHighLowRealRoi($evaluation);
    }

    public function isPriceInRange(float $price, float $high, float $low): bool
    {
        return $price <= $high && $price >= $low;
    }

    public function evaluate(TradeSetup|Signal $entry, TradeSetup|Signal $exit): Evaluation
    {
        $evaluation = $this->setupEvaluation($entry, $exit);

        if ($this->validateEntryPrice($evaluation))
        {
            $this->realizeTrade($evaluation);
        }

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
        if ($evaluation->is_ambiguous)
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