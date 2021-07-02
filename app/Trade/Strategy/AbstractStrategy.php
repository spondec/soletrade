<?php

namespace App\Trade\Strategy;

use App\Models\Candles;
use App\Models\TradeSetup;
use App\Trade\Indicator\FibonacciRetracement;
use App\Trade\Indicator\MACD;
use App\Trade\Indicator\RSI;
use App\Trade\VersionableInterface;

abstract class AbstractStrategy implements VersionableInterface
{
    const ALLOWED_INTERVALS = [];

    /** @var int - seconds */
    const SCAN_INTERVAL = 300;

    const INDICATORS = [
        MACD::class => [],
        RSI::class => [],
        FibonacciRetracement::class => []
    ];



    protected function initIndicators(Candles $candles): void
    {
        foreach (self::INDICATORS as $class => $config)
        {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            $candles->addIndicator(new $class($candles, $config));
        }
    }

    public function check(Candles $candles): ?TradeSetup
    {
        $this->initIndicators($candles);


    }
}