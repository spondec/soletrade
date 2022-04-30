<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;

final class MA extends Indicator
{
    protected array $config = ['timePeriod' => 8];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($sma = \trader_sma($candles->closes(), $this->config['timePeriod'])) ? $sma : [];
    }
}