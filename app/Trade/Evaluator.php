<?php

namespace App\Trade;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use Illuminate\Support\Facades\App;

class Evaluator
{
    //TODO Will migrate this to a model
    protected array $result = [
        'realized_roi'  => 0,
        'highest_roi'   => 0,
        'lowest_roi'    => 0,
        'highest_price' => 0,
        'lowest_price'  => 0,
        'ambiguous'     => false,
        'stop'          => false,
        'close'         => false,
    ];

    protected SymbolRepository $symbolRepo;

    public function __construct(protected TradeSetup|Signal $entry,
                                protected TradeSetup|Signal $exit)
    {

        $this->symbolRepo = App::make(SymbolRepository::class);

        $this->assertEntryExitTime();
    }

    protected function assertEntryExitTime(): void
    {
        if ($this->exit->timestamp <= $this->entry->timestamp)
        {
            throw new \LogicException('Exit trade must not be newer than entry trade.');
        }
    }

    protected function validateEntryPrice(): bool
    {
        $entryPrice = $this->entry->price;

        $candle = $this->symbolRepo->fetchLowestHighestPriceBetween($this->entry->symbol_id,
            $this->entry->timestamp,
            $this->exit->timestamp);

        $this->result['highest_price'] = $candle->h;
        $this->result['lowest_price'] = $candle->l;

        $this->entry->valid_price = $isValid = ($entryPrice >= $candle->l && $entryPrice <= $candle->h);
        $this->entry->save();

        return $isValid;
    }

    protected function realizeTrade(): void
    {
        $candles = $this->symbolRepo->fetchCandlesBetween($this->entry->symbol_id,
            $this->entry->timestamp,
            $this->exit->timestamp);

        $lowestEntry = INF;
        $highestEntry = 0;
        $realEntryTime = null;

        $entryPrice = $this->entry->price;
        $stopPrice = $this->entry->stop_price;
        $closePrice = $this->entry->close_price;

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
                    $this->result['highest_entry'] = (float)$highestEntry;
                    $this->result['lowest_entry'] = (float)$lowestEntry;
                    $this->result['entry_time'] = $realEntryTime;
                }
            }

            if ($stopPrice && $this->isPriceInRange($stopPrice, $high, $low))
            {
                $this->result['stop'] = true;
                $break = true;
            }

            //TODO handle take profits

            if ($closePrice && $this->isPriceInRange($closePrice, $high, $low))
            {
                $this->result['close'] = true;

                if ($break)
                {
                    $this->result['ambiguous'] = true;
                }

                $break = true;
            }

            if ($break ?? false)
            {
                $this->setCloseTime($candle->t);
                break;
            }
        }
    }

    public function isPriceInRange(float $price, float $high, float $low): bool
    {
        return $price <= $high && $price >= $low;
    }

    protected function setCloseTime(int $timestamp): void
    {
        $this->result['close_time'] = $timestamp;
    }

    public function evaluate(): array
    {
        if (!$this->validateEntryPrice())
        {
            return $this->result;
        }

        $this->realizeTrade();

        if ($this->result['ambiguous'])
        {
            return $this->result;
        }

        $this->calcHighLowRealRoi();

        return $this->result;
    }

    protected function getExitPrice(): float
    {
        if ($this->result['stop'])
        {
            return $this->entry->stop_price;
        }

        if ($this->result['close'])
        {
            return $this->entry->close_price;
        }

        return $this->exit->price;
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

    protected function calcHighLowRealRoi(): void
    {
        $side = $this->entry->side;
        $entryPrice = $this->entry->price;
        $buy = $this->entry->side === Signal::BUY;

        $exitPrice = $this->getExitPrice();

        $this->result['realized_roi'] = $this->calcRoi($side, $entryPrice, $exitPrice);
        $this->result['highest_roi'] = $this->calcRoi($side, $entryPrice,
            $buy ? $this->result['highest_price'] : $this->result['lowest_price']);
        $this->result['lowest_roi'] = $this->calcRoi($side, $entryPrice,
            !$buy ? $this->result['highest_price'] : $this->result['lowest_price']);
    }
}