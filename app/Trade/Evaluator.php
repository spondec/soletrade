<?php

namespace App\Trade;

use App\Models\Signal;
use App\Models\TradeSetup;
use Illuminate\Support\Facades\DB;

class Evaluator
{
    protected array $result = []; //TODO Will migrate this to a model

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

        $this->result['highest_price'] = $highest = (float)$candle->h;
        $this->result['lowest_price'] = $lowest = (float)$candle->l;

        $stop = ($stopPrice = $this->entry->stop_price) && (
                ($buy && $lowest <= $stopPrice) ||
                (!$buy && $highest >= $stopPrice)
            );
        $this->result['stop'] = $stop;
        $this->result['stop_price'] = $stopPrice;

        if ($validPrice = ($entryPrice >= $lowest && $entryPrice <= $highest))
        {
            $this->entry->valid_price = true;
            $this->entry->save();

            $this->realizeEntry();
        }

        $this->result['realized_roi'] = $validPrice ? $this->calcRoi($side, $entryPrice, $stop ? $stopPrice : $this->exit->price) : 0;
        $this->result['highest_roi'] = $validPrice ? $this->calcRoi($side, $entryPrice, $buy ? $highest : $lowest) : 0;
        $this->result['lowest_roi'] = $validPrice ? $this->calcRoi($side, $entryPrice, !$buy ? $highest : $lowest) : 0;

        //TODO:: handle take profits
        return $this->result;
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

    protected function realizeEntry(): void
    {
        $candles = $this->getCandlesBetween();

        $lowestEntry = INF;
        $highestEntry = 0;
        $entryPrice = $this->entry->price;
        $realEntryTime = null;

        foreach ($candles as $candle)
        {
            $low = $candle->l;
            $high = $candle->h;

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
                break;
            }
        }

        $this->result['highest_entry'] = (float)$highestEntry;
        $this->result['lowest_entry'] = (float)$lowestEntry;
        $this->result['entry_time'] = $realEntryTime;
    }

    public function calcRoi(string $side, int|float $entryPrice, int|float $exitPrice): int|float
    {
        $roi = ($exitPrice - $entryPrice) * 100 / $entryPrice;

        if ($side === Signal::SELL) $roi *= -1;

        return round($roi, 2);
    }

    protected function getCandlesBetween(): \Illuminate\Support\Collection
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
        return $candles;
    }
}