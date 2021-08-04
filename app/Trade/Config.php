<?php

namespace App\Trade;

use App\Trade\Exchange\AbstractExchange;
use App\Trade\Exchange\Spot\Binance;
use App\Trade\Indicator\AbstractIndicator;
use App\Trade\Indicator\MACD;
use App\Trade\Indicator\RSI;
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
        return [RSI::class, MACD::class];
    }

    /**
     * @return string[]
     */
    public static function symbols(): array
    {
        $symbols = [];

        foreach (static::exchanges() as $exchange)
        {
            $exchange = $exchange::instance();
//            $symbols[$exchange::class] = $exchange->symbols();
            $symbols[$exchange::class] = DB::table('symbols')
                ->distinct()
                ->where('exchange_id', $exchange->id())
                ->get('symbol')
                ->pluck('symbol')
                ->toArray();
        }

        return $symbols;
    }
}