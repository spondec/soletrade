<?php

namespace App\Trade\Indicator;

use App\Trade\Collection\CandleCollection;

class SMA extends Indicator
{
    protected array $config = ['timePeriod' => 8];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($sma = \trader_sma($candles->closes(), $this->config['timePeriod'])) ? $sma : [];
    }
}