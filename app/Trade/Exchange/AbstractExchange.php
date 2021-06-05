<?php

namespace App\Trade\Exchange;

use App\Models\Order;

abstract class AbstractExchange
{
    const BUY_LONG = true;
    const SELL_SHORT = false;

    const EXCHANGE_NAME = null;

    public function __construct(protected string $apiKey, protected string $secretKey)
    {
        if (self::EXCHANGE_NAME === null)
        {
            throw new \InvalidArgumentException('EXCHANGE_NAME is not defined.');
        }
    }

    public function name(): string
    {
        return $this->exchangeName;
    }

    abstract public function market(bool $side, string $symbol, float $size): Order|false;

    abstract public function limit(bool $side, string $symbol, float $price, float $size): Order|false;

    abstract public function stopLimit(bool   $side,
                                       string $symbol,
                                       float  $stopPrice,
                                       float  $price,
                                       float  $size): Order|false;

    /**
     * @return Order[]
     */
    abstract public function openOrders(): array;

    abstract public function accountBalance(): AccountBalance;

    abstract public function price(string $symbol): float;

    abstract public function orderBook(string $symbol): OrderBook;

    /**
     * @return string[]
     */
    abstract public function symbolList(): array;

    abstract public function candleMap(): array;

    abstract public function candles(string $symbol, string $interval, float $start, float $end): array;
}