<?php

namespace App\Trade\Indicator;

use App\Trade\CandleCollection;

class EMA extends AbstractIndicator
{
    protected array $config = ['timePeriod' => 8];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($ema = \trader_ema($candles->closes(), $this->config['timePeriod'])) ? $ema : [];
    }
}