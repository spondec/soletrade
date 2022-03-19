<?php

namespace App\Trade\Exchange;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Side;

abstract class Orderer implements \App\Trade\Contracts\Exchange\Orderer
{
    public function __construct(protected Exchange $exchange)
    {

    }

    /**
     * @param Order $order
     *
     * @return Fill[]
     */
    final public function sync(Order $order): array
    {
        $response = $this->executeOrderUpdate($order);
        $fills = $this->handleOrderResponse($order, $response);

        $order->logResponse('update', $response);
        $order->save();

        return $fills;
    }

    /**
     * @param Order $order
     * @param array $response
     *
     * @return Fill[]
     * @throws \LogicException|\UnexpectedValueException
     */
    private function handleOrderResponse(Order $order, array $response): array
    {
        $this->processOrderDetails($order, $response);

        $fills = $this->processOrderFills($order, $response);

        if ($order->filled && !$fills)
        {
            throw new \LogicException('Failed to process order fills.');
        }

        if (!$order->save()) //TODO:: insert or update?
        {
            throw new \UnexpectedValueException('Failed to update order.');
        }

        foreach ($fills as $fill)
        {
            if (!$fill->save()) //TODO:: insert or update?
            {
                throw new \UnexpectedValueException('Failed to update fill.');
            }
        }

        return $fills;
    }

    abstract protected function executeOrderUpdate(Order $order): array;

    final public function cancel(Order $order): Order
    {
        $response = $this->executeOrderCancel($order);
        $this->handleOrderResponse($order, $response);

        $order->logResponse('cancel', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeOrderCancel(Order $order): array;

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

    protected function newOrder(Order $order): Order
    {
        $response = $this->executeNewOrder($order);
        $this->handleOrderResponse($order, $response);

        $order->logResponse('new', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeNewOrder(Order $order): array;

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

    abstract protected function processOrderDetails(Order $order, array $response): void;

    /**
     * @param Order $order
     * @param array $response
     *
     * @return Fill[]
     */
    abstract protected function processOrderFills(Order $order, array $response): array;
}