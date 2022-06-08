<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;

final class ATR extends Indicator
{
    protected array $config = ['timePeriod' => 14];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return \trader_atr($candles->highs(), $candles->lows(), $candles->closes(), $this->config['timePeriod']) ?: [];
    }
}
