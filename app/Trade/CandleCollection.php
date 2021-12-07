<?php

namespace App\Trade;

use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;

class CandleCollection extends Collection
{
    #[Pure] public function closes(): array
    {
        return array_column($this->all(), 'c');
    }

    #[Pure] public function highs(): array

    {
        return array_column($this->all(), 'h');
    }

    #[Pure] public function lows(): array
    {
        return array_column($this->all(), 'l');
    }

    #[Pure] public function timestamps(): array
    {
        return array_column($this->all(), 't');
    }

    public function previousCandles(int $amount, int $startIndex): static
    {
        if ($amount > $startIndex)
        {
            throw new \InvalidArgumentException('Not enough candles exist.');
        }

        $candles = [];
        for ($i = 1; $i <= $amount; $i++)
        {
            $candles[] = $this->items[$startIndex - $i];
        }
        return new static(array_reverse($candles, false));
    }

    public function findPrevNext(int        $timestamp,
                                 ?\stdClass &$prev = null,
                                 ?\stdClass &$next = null,
                                 ?int       &$prevKey = null,
                                 ?int       &$nextKey = null): void
    {
        /**
         * @var int       $key
         * @var \stdClass $candle
         */
        foreach ($this->items as $key => $candle)
        {
            if (!$prev)
            {
                if ($candle->t > $timestamp)
                {
                    $prev = $_prev ?? null;
                    $prevKey = $_prevKey ?? null;
                }
            }
            else if ($candle->t > $prev->t)
            {
                $next = $candle;
                $nextKey = $key;
                break;
            }

            $_prev = $candle;
            $_prevKey = $key;
        }
    }
}