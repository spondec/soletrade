<?php

namespace App\Trade\Indicator;

class EMA extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 8];

    protected function run(): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($ema = \trader_ema($this->candles->closes(), $this->config['timePeriod'])) ? $ema : [];
    }
}