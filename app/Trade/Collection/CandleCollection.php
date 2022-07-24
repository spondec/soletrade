<?php

namespace App\Trade\Collection;

use App\Models\Symbol;
use App\Trade\Calc;
use Illuminate\Support\Collection;

class CandleCollection extends Collection
{
    protected array $overrides = [];

    public function __construct($items = [])
    {
        if (!array_is_list($items))
        {
            throw new \InvalidArgumentException('CandleCollection must be constructed with an ordered list.');
        }

        parent::__construct($items);
    }

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

    public function between(int  $startDate,
                            int  $endDate,
                            bool $includeStart = false): static
    {
        if ($startDate >= $endDate)
        {
            throw new \LogicException('$startDate cannot be greater than or equal to $endDate.');
        }

        return $this
            ->where('t', $includeStart ? '>=' : '>', $startDate)
            ->where('t', '<=', $endDate);
    }

    public function nextCandle(int $timestamp): ?object
    {
        $first = array_key_first($this->items);
        $last = array_key_last($this->items);

        if (!Calc::inRange($timestamp, $this->items[$last]->t, $this->items[$first]->t))
        {
            throw new \RangeException('Timestamp is not within range of candles.');
        }

        $search = binary_search(
            $this->items,
            $timestamp,
            $first,
            $last,
            fn(object $a, int $b) => $a->t <=> $b,
            $low,
            $high
        );

        if ($search !== null)
        {
            if ($candle = $this->items[$search + 1] ?? null)
            {
                return $candle;
            }
            throw new \LogicException('Next of last candle hit.');
        }

        $next = $this->items[$high];

        if ($next->t > $timestamp)
        {
            return $next;
        }
        throw new \LogicException('Timestamp of next candle is not higher than timestamp.');
    }
}