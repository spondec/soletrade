<?php

namespace App\Trade\Exchange\Spot;

use App\Models\Order;
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

    public function stopLimit(bool $side, string $symbol, float $stopPrice, float $price, float $size): Order|false
    {
        // TODO: Implement stopLimit() method.
    }

    public function getOpenOrders(): array
    {
        // TODO: Implement getOpenOrders() method.
    }

    public function getAccountBalance(): AccountBalance
    {
        // TODO: Implement getAccountBalance() method.
    }

    public function getLastPrice(string $symbol): float
    {
        // TODO: Implement getLastPrice() method.
    }

    public function getOrderBook(string $symbol): OrderBook
    {
        // TODO: Implement getOrderBook() method.
    }
}