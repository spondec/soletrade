<?php

namespace App\Trade\Exchange;

use App\Models\Order;

interface IExchange
{
    public const BUY_LONG = true;
    public const SELL_SHORT = false;

    public function name(): string;

    public function market(bool $side, string $symbol, float $size): Order|false;

    public function limit(bool $side, string $symbol, float $price, float $size): Order|false;

    public function stopLimit(bool $side,
                              string $symbol,
                              float $stopPrice,
                              float $price,
                              float $size): Order|false;

    /**
     * @return Order[]
     */
    public function openOrders(): array;

    public function accountBalance(): AccountBalance;

    public function price(string $symbol): float;

    public function orderBook(string $symbol): OrderBook;

    /**
     * @return string[]
     */
    public function symbolList(): array;

    public function candleMap(): array;

    public function candles(string $symbol, string $interval, float $start, float $end): array;
}