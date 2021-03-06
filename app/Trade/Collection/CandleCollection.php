<?php

namespace App\Trade\Collection;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** @property object items */
class CandleCollection extends Collection
{
    protected array $overrides = [];

    public function closes(): array
    {
        return \array_column($this->all(), 'c');
    }

    public function highs(): array

    {
        return \array_column($this->all(), 'h');
    }

    public function lows(): array
    {
        return \array_column($this->all(), 'l');
    }

    public function volumes(): array
    {
        return \array_column($this->all(), 'v');
    }

    public function hasVolume(): bool
    {
        return !empty($this->items[0]->v);
    }

    public function timestamps(): array
    {
        return \array_column($this->all(), 't');
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
        return new static(\array_reverse($candles, false));
    }

    public function findPrevNextCandle(int        $timestamp,
                                       ?object &$prev = null,
                                       ?object &$next = null,
                                       ?int       &$prevKey = null,
                                       ?int       &$nextKey = null): void
    {
        /**
         * @var int       $key
         * @var object $candle
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

    public function overrideCandle(int $key, object $candle): void
    {
        $this->overrides[$key] = $this->items[$key];
        $this->items[$key] = $candle;
    }

    public function forgetOverride(int $key): void
    {
        $this->items[$key] = $this->overrides[$key];
        unset($this->overrides[$key]);
    }
}