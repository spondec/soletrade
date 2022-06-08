<?php

namespace App\Trade\Exchange\FTX;

use App\Trade\Exchange\Exchange;

class Fetcher extends \App\Trade\Exchange\CCXT\Fetcher
{
    public function __construct(Exchange $exchange, \ccxt\ftx $api)
    {
        parent::__construct($exchange, $api);
    }

    public function getMaxCandlesPerRequest(): int
    {
        return 1500;
    }

    protected function getSymbolColumnKey(): string
    {
        return 'id';
    }

    protected function fetchCandles(string $symbol, string $interval, int $start = null, int $limit = null): array
    {
        return parent::fetchCandles($symbol, $interval, !$start ? null : $start, $limit);
    }
}
