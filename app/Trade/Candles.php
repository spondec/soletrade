<?php

namespace App\Trade;

use App\Trade\Collection\CandleCollection;

/**
 * DO NOT USE \Iterator::current() IN THIS CLASS
 * CURRENT ITEM MIGHT BE OVERRIDDEN!!!
 * USE $this->candles[\Iterator::key()] INSTEAD
 *
 * @see CandleCollection::overrideCandle()
 */
class Candles
{
    public function __construct(protected \Iterator        $iterator,
                                protected CandleCollection $candles)
    {
    }

    public function candle(): \stdClass
    {
        return $this->candles[$this->iterator->key()];
    }

    public function lowest(int $period): float
    {
        return \min(\array_column($this->get($period), 'l'));
    }

    public function get(int $period): array
    {
        return \array_slice($this->candles->all(), $this->iterator->key() - $period, $period);
    }

    public function highest(int $period): float
    {
        return \max(\array_column($this->get($period), 'h'));
    }

    public function lowestClose(int $period): float
    {
        return \min(\array_column($this->get($period), 'c'));
    }

    public function highestClose(int $period): float
    {
        return \max(\array_column($this->get($period), 'c'));
    }
}