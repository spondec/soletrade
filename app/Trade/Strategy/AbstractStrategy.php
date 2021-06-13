<?php

namespace App\Trade\Strategy;

//TODO:: düşüşlerden destek bulma, hangi indikatörlerin desteği başarılı vs.
//TODO:: kısa vadede en güzel destek nedir?
//TODO:: Fib çizgisinin hemen üstünde ya da altında destek/direnç var mı?

//TODO:: saatlik/4 saatlik destekten al, kırarsa sat? supertrend

//TODO:: dirençten short stratejisi: eğer güçlü bir direnç ise shortta kal, değilse scalp ile yetin
//TODO:: fundamental haber geldiğinde paritedeki artış son 10 mumun artış ortalamasından yüksekse işleme gir

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