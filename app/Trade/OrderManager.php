<?php

declare(strict_types=1);

namespace App\Trade;

use App\Models\Fill;
use App\Models\Order;
use App\Models\Symbol;
use App\Trade\Contracts\Exchange\Orderer;
use App\Trade\Exchange\Exchange;
use JetBrains\PhpStorm\Pure;

class OrderManager
{
    public ?Order $entry = null;
    public ?Order $exit = null;
    public ?Order $stop = null;

    /**
     * @var array<Order>
     */
    protected array $orders = [];

    public function __construct(protected Exchange $exchange,
                                protected Symbol   $symbol)
    {
    }

    /**
     * @param Order $order
     *
     * @return Fill[]
     */
    public function sync(Order $order): array
    {
        return $this->order()->sync($order);
    }

    #[Pure] protected function order(): Orderer
    {
        return $this->exchange->order();
    }

    public function cancel(Order $order): Order
    {
        return $this->order()->cancel($order);
    }

    public function market(Side $side, float $quantity, bool $reduceOnly): Order
    {
        return $this->new($this->order()
            ->market($side, $this->symbol->symbol, $quantity, $reduceOnly));
    }

    protected function new(Order $order): Order
    {
        $this->registerOrderListeners($order);

        return $this->orders[] = $order;
    }

    protected function registerOrderListeners(Order $order): void
    {
    }

    public function stopMarket(Side  $side,
                               float $quantity,
                               float $stopPrice,
                               bool  $reduceOnly): Order
    {
        return $this->new($this->order()
            ->stopMarket($side, $this->symbol->symbol, $quantity, $stopPrice, $reduceOnly));
    }

    public function limit(Side  $side,
                          float $price,
                          float $quantity,
                          bool  $reduceOnly): Order
    {
        return $this->new($this->order()
            ->limit($side, $this->symbol->symbol, $price, $quantity, $reduceOnly));
    }

    public function stopLimit(Side  $side,
                              float $stopPrice,
                              float $price,
                              float $quantity,
                              bool  $reduceOnly): Order
    {
        return $this->new($this->order()
            ->stopLimit($side, $this->symbol->symbol, $stopPrice, $price, $quantity, $reduceOnly));
    }
}
