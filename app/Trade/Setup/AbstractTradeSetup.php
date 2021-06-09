<?php

namespace App\Trade\Setup;

use App\Models\Candles;
use App\Trade\Indicator\FibonacciRetracement;
use App\Trade\Indicator\MACD;
use App\Trade\Indicator\RSI;

abstract class AbstractTradeSetup
{


    public function __construct(protected Candles $candles, bool $side)
    {
    }

    public function get()
    {

    }

    public function getSuccessRate(): float
    {

    }
}