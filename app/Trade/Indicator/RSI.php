<?php

namespace App\Trade\Indicator;

use App\Trade\CandleCollection;

class RSI extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 14];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($rsi = \trader_rsi($candles->closes(), $this->config['timePeriod'])) ? $rsi : [];
    }
}