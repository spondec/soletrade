<?php

namespace App\Trade\Indicator;

class IndicatorSeriesState
{
    /**
     * Initial gap with the candles.
     *
     * @var int
     */
    public int $gap = 0;

    /**
     * Current candle index.
     *
     * @var int
     */
    public int $index = 0;

    /**
     * Target column name if the value is an array, otherwise null.
     *
     * @var string|null
     */
    public ?string $column = null;
}