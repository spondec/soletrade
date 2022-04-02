<?php

namespace App\Trade\Exchange\Binance\Spot;

class Fetcher extends \App\Trade\Exchange\CCXT\Fetcher
{
    public function getMaxCandlesPerRequest(): int
    {
        return 1000;
    }

    protected function getSymbolColumnKey(): string
    {
        return 'symbol';
    }
}