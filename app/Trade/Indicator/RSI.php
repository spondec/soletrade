<?php

namespace App\Trade\Indicator;

class RSI extends AbstractIndicator
{
    protected array $config = ['timeFrame' => 14];

    protected function run(): array
    {
        return $this->combineTimestamps(\trader_rsi($this->closes(), $this->config['timeFrame']));
    }
}