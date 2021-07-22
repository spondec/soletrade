<?php

namespace App\Trade;

use App\Trade\Exchange\AbstractExchange;
use App\Trade\Exchange\Spot\Binance;

final class Config
{
    /**
     * @return AbstractExchange[]
     */
    public static function exchanges(): array
    {
        return [Binance::class];
    }
}