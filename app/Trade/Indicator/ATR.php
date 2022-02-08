<?php

namespace App\Trade\Indicator;

use App\Trade\CandleCollection;

class ATR extends Indicator
{
    protected array $config = ['timePeriod' => 14];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return \trader_atr($candles->highs(), $candles->lows(), $candles->closes(), $this->config['timePeriod']) ?: [];
    }
}