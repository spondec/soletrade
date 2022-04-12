<?php

declare(strict_types=1);

namespace App\Trade;

use App\Models\Fill;
use App\Models\Order;
use App\Models\OrderType;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Contracts\Exchange\Orderer;
use App\Trade\Exchange\Exchange;
use App\Trade\Order\Type\Handler;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;

class OrderManager
{
    public ?Order $entry = null;
    public ?Order $exit = null;
    public ?Order $stop = null;

    /**
     * @var Order[]
     */
    protected Collection $orders;

    public function syncAll(): void
    {
        foreach ($this->orders as $order)
        {
            if ($order->isOpen())
            {
                $this->sync($order);
            }
        }
    }

    public function cancelAll(): void
    {
        foreach ($this->orders as $order)
        {
            $this->cancel($order);
        }
    }

    public function __construct(protected Exchange   $exchange,
                                protected Symbol     $symbol,
                                public               readonly TradeAsset $tradeAsset,
                                protected TradeSetup $trade)
    {
        $this->orders = new Collection();
    }

    public function orders(): Collection
    {
        return $this->orders;
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

    /**
     * @param Order $order
     *
     * @return Order
     * @throws \App\Exceptions\OrderNotCanceledException
     * @throws \App\Exceptions\OrderFilledInCancelRequest
     */
    public function cancel(Order $order): Order
    {
        $this->sync($order);

        if (!$order->isOpen())
        {
            return $order;
        }

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
        $order->onCancel(function (Order $order) {

            if ($order === $this->stop)
            {
                $this->stop = null;
            }
            if ($order === $this->entry)
            {
                $this->entry = null;
            }
            if ($order === $this->exit)
            {
                $this->exit = null;
            }
        });
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

    public function handler(OrderType $orderType, Side $side): Handler
    {
        return new (Handler::getClass($orderType))(side: $side,
            manager: $this,
            config: $this->trade->order_type_config[$orderType->value] ?? []);
    }

    public function __destruct()
    {
        foreach ($this->orders as $order)
        {
            if (!$order->isOpen())
            {
                $order->flushListeners();
            }
        }
    }
}
