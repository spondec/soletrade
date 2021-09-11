<?php

namespace App\Trade\Indicator;

class EMA extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 2];

    protected function run(): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($ema = \trader_ema($this->closes(), $this->config['timePeriod'])) ? $ema : [];
    }
}