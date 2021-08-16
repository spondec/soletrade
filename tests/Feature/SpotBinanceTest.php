<?php

namespace Tests\Feature;

use App\Trade\Exchange\Spot\Binance;

class SpotBinanceTest extends ExchangeTest
{
    protected static string $testSymbol = 'BTC/USDT';

    protected function setupExchange(): Binance
    {
        $this->exchange = Binance::instance();
        $this->setSandboxMode(true);

        return $this->exchange;
    }

    protected function setSandboxMode(bool $enabled): void
    {
        /** @var \ccxt\binance $api */
        $api = $this->exchange->getApi();
        $api->set_sandbox_mode($enabled);
    }

    protected function updaterSetUp(): void
    {
        parent::updaterSetUp();
        $this->setSandboxMode(false);
    }

    protected function getSymbol(): string
    {
        return static::$testSymbol;
    }

    protected function getQuantity(string $symbol): float
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
