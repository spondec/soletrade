<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;

class MFI extends Indicator
{
    protected array $config = [
        'timePeriod' => 14
    ];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        return \trader_mfi($candles->highs(),
            $candles->lows(),
            $candles->closes(),
            $candles->volumes(),
            $this->config('timePeriod')) ?: [];
    }
}