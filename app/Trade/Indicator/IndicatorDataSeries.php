<?php

namespace App\Trade\Indicator;

use App\Trade\Collection\CandleCollection;
use App\Trade\Contract\Series;
use Illuminate\Support\Collection;
use function abs;

class IndicatorDataSeries implements Series
{
    /**
     * @param IndicatorSeriesState $state   Indicator value pointer.
     * @param Collection           $data    Indicator generated values keyed with timestamps.
     * @param CandleCollection     $candles Candles used for the values.
     */
    public function __construct(protected IndicatorSeriesState $state,
                                protected Collection           $data,
                                protected CandleCollection     $candles)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return (bool)$this->value($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->value($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('IndicatorData is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('IndicatorData is immutable.');
    }

    public function value(int $offset = 0, ?string $column = null): Series
    {
        if ($offset == 0 && !$column)
        {
            return $this;
        }

        $series = clone $this;

        if ($column)
        {
            $series->state->column = $column;
        }

        if ($offset != 0)
        {
            $series->state->index += -abs($offset);
        }

        return $series;
    }

    public function __clone(): void
    {
        $this->state = clone $this->state;
    }

    public function candle(int $offset = 0): ?object
    {
        return $this->candles[$this->state->index + $this->state->gap + -abs($offset)] ?? null;
    }

    public function get(int $offset = 0): mixed
    {
        $data = $this->data[$this->candle($offset)?->t] ?? null;

        if ($data && $this->state->column)
        {
            return $data[$this->state->column] ?? null;
        }

        return $data;
    }
}