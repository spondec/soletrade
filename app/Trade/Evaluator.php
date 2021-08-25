<?php

namespace App\Trade;

use App\Models\Signal;
use App\Models\TradeSetup;
use Illuminate\Support\Facades\DB;

class Evaluator
{
    public function __construct(protected TradeSetup|Signal $entry,
                                protected TradeSetup|Signal $exit)
    {

        $this->assertEntryExitTime();
    }

    protected function assertEntryExitTime(): void
    {
        if ($this->exit->timestamp <= $this->entry->timestamp)
        {
            throw new \LogicException('Exit trade must be older than entry trade.');
        }
    }

    public function evaluate(): array
    {
        $side = $this->entry->side;
        $entryPrice = $this->entry->price;
        $buy = $side === Signal::BUY;

        $candle = $this->getLowestHighestPriceBetween();

        $result['highest_price'] = $highest = (float)$candle->h;
        $result['lowest_price'] = $lowest = (float)$candle->l;

        $stop = ($stopPrice = $this->entry->stop_price) && (
                ($buy && $lowest <= $stopPrice) ||
                (!$buy && $highest >= $stopPrice)
            );
        $result['stop'] = $stop;
        $result['stop_price'] = $stopPrice;

        if ($validPrice = ($entryPrice >= $lowest && $entryPrice <= $highest))
        {
            $this->entry->valid_price = true;
            $this->entry->save();

            $result = array_merge($result, $this->getLowestHighestUntilEntryPrice());
        }

        $roi = [
            'realized_roi' => $validPrice ? $this->calcRoi($side, $entryPrice, $stop ? $stopPrice : $this->exit->price) : 0,
            'highest_roi'  => $validPrice ? $this->calcRoi($side, $entryPrice, $buy ? $highest : $lowest) : 0,
            'lowest_roi'   => $validPrice ? $this->calcRoi($side, $entryPrice, !$buy ? $highest : $lowest) : 0,
        ];

        //TODO:: handle take profits
        return array_merge($result, $roi);
    }

    protected function getLowestHighestPriceBetween(): object
    {
        $candle = DB::table('candles')
            ->select(DB::raw('max(h) as h, min(l) as l'))
            ->where('symbol_id', $this->entry->symbol_id)
            ->where('t', '>=', $this->entry->timestamp)
            ->where('t', '<=', $this->exit->timestamp)
            ->first();

        if (!$candle)
        {
            throw new \UnexpectedValueException('Highest/lowest price between the two trades was not found.');
        }

        return $candle;
    }

    protected function getLowestHighestUntilEntryPrice(): array
    {
        $candles = DB::table('candles')
            ->where('symbol_id', $this->entry->symbol_id)
            ->where('t', '>=', $this->entry->timestamp)
            ->where('t', '<=', $this->exit->timestamp)
            ->orderBy('t', 'ASC')
            ->get();

        if (!$candles)
        {
            throw new \UnexpectedValueException($this->entry->symbol()->first()->name . ' candles are missing!');
        }

        $lowestUntilEntry = INF;
        $highestUntilEntry = 0;
        $entryPrice = $this->entry->price;
        $realEntryTime = null;

        foreach ($candles as $candle)
        {
            $low = $candle->l;
            $high = $candle->h;

            if ($low < $lowestUntilEntry)
            {
                $lowestUntilEntry = $low;
            }

            if ($high > $highestUntilEntry)
            {
                $highestUntilEntry = $high;
            }

            if ($entryPrice >= $low && $entryPrice <= $high)
            {
                $realEntryTime = (float)$candle->t;
                break;
            }
        }

        return [
            'highest_until_entry' => (float)$highestUntilEntry,
            'lowest_until_entry'  => (float)$lowestUntilEntry,
            'real_entry_time'     => $realEntryTime
        ];
    }

    protected function calcRoi(string $side, int|float $entryPrice, int|float $exitPrice): int|float
    {
        $roi = ($exitPrice - $entryPrice) * 100 / $entryPrice;

        if ($side === Signal::SELL) $roi *= -1;

        return round($roi, 2);
    }
}