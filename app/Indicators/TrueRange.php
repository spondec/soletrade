<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;

class TrueRange extends Indicator
{
    protected array $config = [
        'timePeriod' => 14
    ];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return \trader_trange($candles->highs(),
            $candles->lows(),
            $candles->closes(),
            $this->config('timePeriod')) ?: [];
    }
}