<?php

namespace App\Trade\Indicator;

class RSI extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 14];

    protected function run(): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($rsi = \trader_rsi($this->candles->closes(), $this->config['timePeriod'])) ? $rsi : [];
    }
}