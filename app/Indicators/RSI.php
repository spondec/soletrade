<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;

final class RSI extends Indicator
{
    protected array $config = ['timePeriod' => 14];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($rsi = \trader_rsi($candles->closes(), $this->config['timePeriod'])) ? $rsi : [];
    }
}