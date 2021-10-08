<?php
/** @noinspection UnnecessaryCastingInspection */
/** @noinspection PhpCastIsUnnecessaryInspection */

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class Evaluator
{
    protected SymbolRepository $symbolRepo;

    public function __construct()
    {
        $this->symbolRepo = App::make(SymbolRepository::class);
    }

    public function evaluate(TradeSetup|Signal $entry, TradeSetup|Signal $exit): Evaluation
    {
        $this->realizeTrade($evaluation = $this->setup($entry, $exit));

        return $evaluation->updateUniqueOrCreate();
    }

    protected function realizeTrade(Evaluation $evaluation): void
    {
        $this->assertExitSignal($evaluation);

        $repo = $this->symbolRepo;

        $entry = $evaluation->entry;
        $exit = $evaluation->exit;

        $symbol = $entry->symbol;
        $symbolId = $symbol->id;

        $firstCandle = $repo->fetchNextCandle($symbolId, $entry->timestamp);
        $lastCandle = $repo->fetchNextCandle($symbolId, $exit->timestamp);

        //fetch 1m candles to minimize the ambiguity
        $candles = $repo->fetchCandles($symbol, $firstCandle->t, $lastCandle->t, '1m');
        $lowHigh = $repo->getLowestHighest($candles);

        $evaluation->highest_price = $lowHigh['highest']->h;
        $evaluation->lowest_price = $lowHigh['lowest']->l;

        $lowestEntry = INF;
        $highestEntry = 0;
        $lowest = INF;
        $highest = 0;
        $realEntryTime = null;

        $savePoint = new SavePointAccess($entry, $firstCandle->t, $lastCandle->t);

        $entered = $stopped = $closed = $exited = false;

        $riskRewardHistory = [];
        $buy = $entry->side === Signal::BUY;

        foreach ($candles as $candle)
        {
            $low = (float)$candle->l;
            $high = (float)$candle->h;
            $timestamp = (int)$candle->t;

            if (!$realEntryTime)
            {
                $entryPrice = $savePoint->lastPointOrAttribute('price', $timestamp);

                if ($low < $lowestEntry) $lowestEntry = $low;
                if ($high > $highestEntry) $highestEntry = $high;

                if (Calc::inRange($entryPrice, $high, $low))
                {
                    $evaluation->is_entry_price_valid = $entered = true;
                    $evaluation->entry_timestamp = $realEntryTime = $timestamp;
                    $evaluation->highest_entry_price = $highestEntry;
                    $evaluation->lowest_entry_price = $lowestEntry;
                }
            }

            if ($entered)
            {
                $newLow = $low < $lowest;
                $newHigh = $high > $highest;

                if ($newLow || $newHigh)
                {
                    if ($newLow) $lowest = $low;
                    if ($newHigh) $highest = $high;

                    $riskRewardHistory[$timestamp] = [
                        'ratio'  => round(Calc::riskReward($buy,
                            $entryPrice,
                            $highest,
                            $lowest, $highRoi, $lowRoi), 2),
                        'reward' => $highRoi,
                        'risk'   => $lowRoi
                    ];
                }

                if (!$exited)
                {
                    $stopPrice = $savePoint->lastPointOrAttribute('stop_price', $timestamp);
                    $closePrice = $savePoint->lastPointOrAttribute('close_price', $timestamp);

                    if ($stopPrice && Calc::inRange($stopPrice, $high, $low))
                    {
                        $evaluation->is_stopped = $stopped = true;
                    }
                    if ($closePrice && Calc::inRange($closePrice, $high, $low))
                    {
                        $evaluation->is_closed = $closed = true;

                        if ($stopped) $evaluation->is_ambiguous = true;
                    }
                    if ($stopped || $closed)
                    {
                        $evaluation->exit_timestamp = $timestamp;
                        $exited = true;
                    }
                }
            }
        }

        $evaluation->entry_price = $entryPrice;

        //close/stop price may not register when the entry is unrealized
        //so get it explicitly
        $evaluation->close_price = $closePrice ?? $savePoint->lastPointOrAttribute('close_price', $timestamp);
        $evaluation->stop_price = $stopPrice ?? $savePoint->lastPointOrAttribute('stop_price', $timestamp);

        $evaluation->risk_reward_history = $riskRewardHistory;

        if ($realEntryTime)
        {
            $this->calcHighestLowestPricesToExit($evaluation);
        }

        $this->calcHighLowRealRoi($evaluation);

        if ($entered)
        {
            foreach ($this->findExitEqualsEntry($evaluation) as $prev)
            {
                $this->completePrevExit($prev, $evaluation);
                $prev->save();
            }
        }
    }

    protected function assertExitSignal(Evaluation $evaluation): void
    {
        if (!$evaluation->exit)
        {
            throw new \InvalidArgumentException('Exit signal/setup does not exist.');
        }
    }

    /**
     * @param Evaluation $evaluation
     */
    protected function calcHighestLowestPricesToExit(Evaluation $evaluation): void
    {
        $repo = $this->symbolRepo;
        $symbol = $evaluation->entry->symbol;
        $entryTime = $evaluation->entry_timestamp;
        $exitTime = $evaluation->exit->timestamp;

        if (empty($entryTime) || empty($exitTime))
        {
            throw new \LogicException('Entry and exit must be timestamped.');
        }

        if ($entryTime >= $exitTime)
        {
            return;
        }

        $candles = $repo->fetchCandles($symbol, $entryTime, $exitTime, '1m');
        $lowHigh = $repo->getLowestHighest($candles);
        $lowest = $lowHigh['lowest'];
        $highest = $lowHigh['highest'];

        if ($lowest->t > $entryTime)
        {
            $evaluation->lowest_price_to_highest_exit = $repo->getLowestHighest($repo->fetchCandles(
                $symbol, $entryTime, $highest->t, '1m'))['lowest']->l;
        }

        if ($highest->t > $entryTime)
        {
            $evaluation->highest_price_to_lowest_exit = $repo->getLowestHighest($repo->fetchCandles(
                $symbol, $entryTime, $lowest->t, '1m'))['highest']->h;
        }
    }

    protected function calcHighLowRealRoi(Evaluation $evaluation): void
    {
        if (!$evaluation->is_entry_price_valid || $evaluation->is_ambiguous)
        {
            return;
        }

        $side = $evaluation->entry->side;
        $entryPrice = (float)$evaluation->entry_price;
        $buy = $evaluation->entry->side === Signal::BUY;

        $evaluation->highest_roi = Calc::roi($side, $entryPrice,
            (float)($buy ? $evaluation->highest_price : $evaluation->lowest_price));
        $evaluation->lowest_roi = Calc::roi($side, $entryPrice,
            (float)(!$buy ? $evaluation->highest_price : $evaluation->lowest_price));
        $evaluation->lowest_to_highest_roi = Calc::roi($side, $entryPrice,
            (float)($buy ? $evaluation->lowest_price_to_highest_exit : $evaluation->highest_price_to_lowest_exit));

        if (!$exitPrice = $this->getExitPrice($evaluation))
        {
            //We'll calculate the realized ROI after the exit price
            // is validated in the subsequent evaluations.
            return;
        }

        $evaluation->realized_roi = Calc::roi($side, $entryPrice, $exitPrice);
    }

    protected function getExitPrice(Evaluation $evaluation): float|null
    {
        if ($evaluation->is_stopped)
        {
            return (float)$evaluation->stop_price;
        }

        if ($evaluation->is_closed)
        {
            return (float)$evaluation->close_price;
        }

        if ($evaluation->is_exit_price_valid)
        {
            return (float)$evaluation->exit_price;
        }

        return null;
    }

    protected function findExitEqualsEntry(Evaluation $evaluation): Collection
    {
        return Evaluation::query()->with(['entry', 'exit'])
            ->where('type', $evaluation->type)
            ->where('exit_id', $evaluation->entry_id)
            ->get();
    }

    protected function completePrevExit(Evaluation $prev, Evaluation $current): void
    {
        $prev->exit_price = $current->entry_price;

        if ($prev->is_exit_price_valid = $current->is_entry_price_valid)
        {
            if (!$prev->exit_timestamp)
            {
                $prev->exit_timestamp = $current->entry_timestamp;
            }

            $this->calcHighLowRealRoi($prev);
        }
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


}