<?php

namespace App\Trade;

use App\Trade\Exchange\AbstractExchange;
use App\Trade\Exchange\Spot\Binance;
use App\Trade\Indicator\AbstractIndicator;
use App\Trade\Indicator\ATR;
use App\Trade\Indicator\EMA;
use App\Trade\Indicator\Fib;
use App\Trade\Indicator\MACD;
use App\Trade\Indicator\RSI;
use App\Trade\Indicator\SMA;
use App\Trade\Strategy\FibScalp;
use Illuminate\Support\Facades\DB;

final class Config
{
    /**
     * @return AbstractExchange[]|string[]
     */
    public static function exchanges(): array
    {
        return [Binance::class];
    }

    /**
     * @return AbstractIndicator[]|string[]
     */
    public static function indicators(): array
    {
        return [
            RSI::class,
            MACD::class,
            Fib::class,
            ATR::class,
            SMA::class,
            EMA::class
        ];
    }

    public static function strategies(): array
    {
        return [FibScalp::class];
    }

    /**
     * @return string[]
     */
    public static function symbols(): array
    {
        return array_map(static function (string $exchange) {
            return DB::table('symbols')
                ->distinct()
                ->where('exchange_id', $exchange::instance()->id())
                ->get('symbol')
                ->pluck('symbol')
                ->toArray();
        }, array_combine(array_map(static fn($e) => $e::name(), $exchanges = static::exchanges()), $exchanges));
    }
}