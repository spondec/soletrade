<?php

namespace App\Trade\Exchange;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Side;

abstract class Orderer implements \App\Trade\Contracts\Exchange\Orderer
{
    public readonly array $actions;

    public function __construct(protected Exchange $exchange)
    {
        $this->actions = $this->availableOrderActions();
    }

    /**
     * @return string[]
     */
    abstract protected function availableOrderActions(): array;

    /**
     * @param Order $order
     *
     * @return Fill[]
     */
    final public function sync(Order $order): array
    {
        $response = $this->executeOrderUpdate($order);
        $fills = $this->handleOrderUpdateResponse($order, $response);

        foreach ($fills as $fill)
        {
            if (!$fill->exists)
            {
                $fill->save();
            }
        }

        $order->logResponse('update', $response);
        $order->save();

        return $fills;
    }

    abstract protected function executeOrderUpdate(Order $order): array;

    /**
     * @param Order $order
     * @param array $response
     *
     * @return Fill[]
     */
    abstract protected function handleOrderUpdateResponse(Order $order, array $response): array;

    final public function cancel(Order $order): Order
    {
        $response = $this->executeOrderCancel($order);
        $this->handleOrderCancelResponse($order, $response);

        $order->logResponse('cancel', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeOrderCancel(Order $order): array;

    abstract protected function handleOrderCancelResponse(Order $order, array $response): void;

    public function market(Side $side, string $symbol, float $quantity, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->quantity = $quantity;
        $order->type = 'MARKET';

        return $this->newOrder($order);
    }

    protected function setupOrder(Side $side = null, string $symbol = null, bool $reduceOnly = null): Order
    {
        $order = new Order();

        if ($side)
        {
            $order->side = $side->value;
            $this->assertAction($order); //TODO:: overriding validation when no side has been set
        }

        if ($symbol)
        {
            $order->symbol = $symbol;
        }

        if ($reduceOnly !== null)
        {
            $order->reduce_only = $reduceOnly;
        }

        $order->exchange_id = $this->exchange->model()->id;

        return $order;
    }

    final protected function assertAction(Order $order): void
    {
        if (!\in_array($order->side, $this->actions))
        {
            throw new \UnexpectedValueException($this->exchange::name() . " doesn't allow to take action: $order->side.\n
            Available actions: " . \implode(', ', $this->actions));
        }
    }

    protected function newOrder(Order $order): Order
    {
        $response = $this->executeNewOrder($order);
        $this->handleNewOrderResponse($order, $response);

        $order->logResponse('new', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeNewOrder(Order $order): array;

    abstract protected function handleNewOrderResponse(Order $order, array $response): void;

    public function stopMarket(Side $side, string $symbol, float $quantity, float $stopPrice, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->quantity = $quantity;
        $order->stop_price = $stopPrice;
        $order->type = 'STOP_LOSS';

        return $this->newOrder($order);
    }

    public function limit(Side $side, string $symbol, float $price, float $quantity, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->price = $price;
        $order->quantity = $quantity;
        $order->type = 'LIMIT';

        return $this->newOrder($order);
    }

    public function stopLimit(Side $side, string $symbol, float $stopPrice, float $price, float $quantity, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->price = $price;
        $order->quantity = $quantity;
        $order->stop_price = $stopPrice;
        $order->type = 'STOP_LOSS_LIMIT';

        return $this->newOrder($order);
    }

    /**
     * @param Order $order
     * @param array $response
     *
     * @return Fill[]
     */
    abstract protected function updateOrderDetails(Order $order, array $response): array;
}