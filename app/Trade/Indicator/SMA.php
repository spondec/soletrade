<?php

namespace App\Trade\Indicator;

class SMA extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 8];

    protected function run(): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($sma = \trader_sma($this->candles->closes(), $this->config['timePeriod'])) ? $sma : [];
    }
}