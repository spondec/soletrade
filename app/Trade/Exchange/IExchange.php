<?php

namespace App\Trade\Exchange;

use App\Models\Order;

interface IExchange
{
    public const BUY_LONG = true;
    public const SELL_SHORT = false;

    public function market(bool $side, string $symbol, float $size): Order|false;

    public function limit(bool $side, string $symbol, float $price, float $size): Order|false;

    public function stopLimit(bool $side,
                              string $symbol,
                              float $stopPrice,
                              float $price,
                              float $size): Order|false;

    /**
     * @return Order[]
     *
     */
    public function getOpenOrders(): array;

    public function getAccountBalance(): AccountBalance;

    public function getLastPrice(string $symbol): float;

    public function getOrderBook(string $symbol): OrderBook;
}