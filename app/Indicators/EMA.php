<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;

final class EMA extends Indicator
{
    protected array $config = ['timePeriod' => 8];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return ($ema = \trader_ema($candles->closes(), $this->config['timePeriod'])) ? $ema : [];
    }
}