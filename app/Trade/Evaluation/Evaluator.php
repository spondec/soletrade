<?php
/** @noinspection UnnecessaryCastingInspection */
/** @noinspection PhpCastIsUnnecessaryInspection */

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Binding;
use App\Models\Evaluation;
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

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

        $entryTimestamp = $evaluation->entry->timestamp;
        $exitTimestamp = $evaluation->exit->timestamp;
        $symbol = $evaluation->entry->symbol;
        $symbolId = $symbol->id;

        $startDate = $this->getNextCandleOpen($symbolId, $entryTimestamp);
        $endDate = $this->getNextCandleOpen($symbolId, $exitTimestamp);

        $candle = $this->symbolRepo->fetchLowestHighestPriceBetween($symbol, $startDate, $endDate);

        $evaluation->highest_price = $candle->h;
        $evaluation->lowest_price = $candle->l;

        //fetch 1m candles to minimize the ambiguity
        $candles = $this->symbolRepo->fetchCandlesBetween($symbol, $startDate, $endDate, '1m');

        $lowestEntry = INF;
        $highestEntry = 0;
        $entryTime = null;

        $bindings = $evaluation->entry->bindings()->get()->keyBy('column');

        $entered = $stopped = $ambiguous = $closed = false;
        foreach ($candles as $candle)
        {
            $entryPrice = isset($bindings['price'])
                ? $this->getLastValueToTimestamp($bindings['price'], $candle->t)
                : $evaluation->entry->price;

            $stopPrice = isset($bindings['stop_price'])
                ? $this->getLastValueToTimestamp($bindings['stop_price'], $candle->t)
                : $evaluation->entry->stop_price;

            $closePrice = isset($bindings['close_price'])
                ? $this->getLastValueToTimestamp($bindings['close_price'], $candle->t)
                : $evaluation->entry->close_price;

            $low = (float)$candle->l;
            $high = (float)$candle->h;

            if (!$entryTime)
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
                    $entryTime = (int)$candle->t;
                    $evaluation->entry_price = $entryPrice;
                    $evaluation->entry_timestamp = $entryTime;
                    $evaluation->highest_entry_price = (float)$highestEntry;
                    $evaluation->lowest_entry_price = (float)$lowestEntry;
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

        $evaluation->stop_price = $stopPrice;
        $evaluation->close_price = $closePrice;
        $evaluation->is_stopped = $stopped;
        $evaluation->is_closed = $closed;
        $evaluation->is_ambiguous = $ambiguous;
        $evaluation->is_entry_price_valid = $entered;

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

    protected function assertExitSignal(Evaluation $evaluation)
    {
        if (!$evaluation->exit)
        {
            throw new \InvalidArgumentException('Exit signal/setup does not exist.');
        }
    }

    protected function getNextCandleOpen(int $symbolId, int $timestamp): int
    {
        return DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', '>', $timestamp)
            ->orderBy('t', 'ASC')
            ->first()?->t;
    }

    protected function getLastValueToTimestamp(Binding $binding, int $timestamp)
    {
        if ($history = $binding->history)
        {
            foreach ($history as $_timestamp => $_value)
            {
                if ($_timestamp <= $timestamp)
                {
                    $value = $_value;
                }
            }

            return $value;
        }

        return $binding->value;
    }

    public function isPriceInRange(float $price, float $high, float $low): bool
    {
        return $price <= $high && $price >= $low;
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

        $evaluation->highest_roi = $this->calcRoi($side, $entryPrice,
            (float)($buy ? $evaluation->highest_price : $evaluation->lowest_price));
        $evaluation->lowest_roi = $this->calcRoi($side, $entryPrice,
            (float)(!$buy ? $evaluation->highest_price : $evaluation->lowest_price));

        if (!$exitPrice = $this->getExitPrice($evaluation))
        {
            //We'll calculate the realized ROI after the exit price
            // is validated in the subsequent evaluations.
            return;
        }

        $evaluation->realized_roi = $this->calcRoi($side, $entryPrice, $exitPrice);

        //TODO ROIs of after the entry until exit
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

    protected function findExitEqualsEntry(Evaluation $evaluation): \Illuminate\Support\Collection
    {
        return Evaluation::query()->with(['entry', 'exit'])
            ->where('type', $evaluation->type)
            ->where('exit_id', $evaluation->entry_id)
            ->get();
    }

    protected function completePrevExit(Evaluation $prev, Evaluation $current)
    {
        $prev->exit_price = $current->entry_price;

        if ($prev->is_exit_price_valid = $current->is_entry_price_valid)
        {
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