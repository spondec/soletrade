<?php

namespace Tests\Feature;

use App\Trade\Exchange\Spot\Binance as Original;
use Tests\Mock\Trade\Exchange\Spot\Binance as Mock;

class SpotBinanceTest extends ExchangeTest
{
    protected static string $defaultSymbol = 'BTC/USDT';

    protected function setupExchange(): Original|Mock
    {
        $binance = Mock::instance();

        /** @var \ccxt\binance $api */
        $api = $binance->getApi();
        $api->set_sandbox_mode(true);

        return $binance;
    }

    protected function getSymbol(): string
    {
        return static::$defaultSymbol;
    }

    protected function getQuantity(string $symbol)
    {
        return parent::getQuantity($symbol) * 3;
    }

    protected function getLimitBuyPrice(string $symbol): float
    {
        return $this->exchange->orderBook($symbol)->bestBid() / 2;
    }

    protected function getLimitSellPrice(string $symbol): float
    {
        return $this->exchange->orderBook($symbol)->bestAsk() * 2;
    }
}
