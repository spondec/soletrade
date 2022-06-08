<?php

namespace App\Trade;

use App\Models\Symbol;
use App\Trade\Collection\CandleCollection;

/**
 * DO NOT USE \Iterator::current() IN THIS CLASS
 * CURRENT ITEM MIGHT BE OVERRIDDEN!!!
 * USE $this->candles[\Iterator::key()] INSTEAD.
 *
 * @see CandleCollection::overrideCandle()
 */
class Candles
{
    public function __construct(protected \Iterator $iterator,
                                protected CandleCollection $candles,
                                public readonly Symbol $symbol)
    {
    }

    public function candle(): object|null
    {
        return $this->candles[$this->iterator->key()] ?? null;
    }

    public function lowest(int $period): ?float
    {
        return ($items = $this->get($period)) ? \min(\array_column($items, 'l')) : null;
    }

    public function get(int $period): ?array
    {
        $offset = $this->iterator->key() - $period;

        if ($offset < 0) {
            return null;
        }

        return \array_slice($this->candles->all(), $offset, $period);
    }

    public function highest(int $period): ?float
    {
        return ($items = $this->get($period)) ? \max(\array_column($items, 'h')) : null;
    }

    public function lowestClose(int $period): ?float
    {
        return ($items = $this->get($period)) ? \min(\array_column($items, 'c')) : null;
    }

    public function highestClose(int $period): ?float
    {
        return ($items = $this->get($period)) ? \max(\array_column($items, 'c')) : null;
    }
}
