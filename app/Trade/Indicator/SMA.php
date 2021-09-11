<?php

namespace App\Trade\Indicator;

class SMA extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 2];

    protected function run(): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($sma = \trader_sma($this->closes(), $this->config['timePeriod'])) ? $sma : [];
    }
}