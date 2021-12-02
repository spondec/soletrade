<?php

namespace App\Trade\Indicator;

class ATR extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 14];

    protected function run(): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return \trader_atr($this->candles->highs(), $this->candles->lows(), $this->candles->closes(), $this->config['timePeriod']) ?: [];
    }
}