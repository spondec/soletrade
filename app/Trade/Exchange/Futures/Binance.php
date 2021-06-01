<?php

namespace App\Trade\Exchange\Futures;

use App\Models\Order;
use App\Models\Position;
use App\Trade\Exchange\AccountBalance;
use App\Trade\Exchange\OrderBook;

class Binance implements IExchange
{
    public function market(bool $side, string $symbol, float $size): Order|false
    {
        // TODO: Implement market() method.
    }

    public function limit(bool $side, string $symbol, float $price, float $size): Order|false
    {
        // TODO: Implement limit() method.
    }

    public function stopLimit(bool $side,
                              string $symbol,
                              float $stopPrice,
                              float $price,
                              float $size): Order|false
    {
        // TODO: Implement stopLimit() method.
    }

    public function getOpenPositions(): array
    {
        // TODO: Implement getOpenPositions() method.
    }

    public function getMarkPrice(string $symbol): float
    {
        // TODO: Implement getMarkPrice() method.
    }

    public function openOrders(): array
    {
        // TODO: Implement getOpenOrders() method.
    }

    public function accountBalance(): AccountBalance
    {
        // TODO: Implement getAccountBalance() method.
    }

    public function price(string $symbol): float
    {
        // TODO: Implement getLastPrice() method.
    }

    public function orderBook(string $symbol): OrderBook
    {
        // TODO: Implement getOrderBook() method.
    }

    public function name(): string
    {
        // TODO: Implement getExchangeName() method.
    }

    public function symbolList(): array
    {
        // TODO: Implement getSymbolList() method.
    }

    public function candleMap(): array
    {
        // TODO: Implement getCandleMap() method.
    }

    public function candles(string $symbol, string $interval, float $start, float $end): array
    {
        // TODO: Implement getCandlesForSymbol() method.
    }
}