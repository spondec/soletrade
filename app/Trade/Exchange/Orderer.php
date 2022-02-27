<?php

namespace App\Trade\Exchange;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

abstract class Orderer
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

    final public function sync(Order $order): Order
    {
        $response = $this->executeOrderUpdate($order);
        $this->handleOrderUpdateResponse($order, $response);

        $order->logResponse('update', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeOrderUpdate(Order $order): array;

    abstract protected function handleOrderUpdateResponse(Order $order, array $response): void;

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

    public function market(string $side, string $symbol, float $quantity): Order
    {
        $order = $this->setupOrder($side, $symbol);

        $order->quantity = $quantity;
        $order->type = 'MARKET';

        return $this->newOrder($order);
    }

    protected function setupOrder(string $side = null, string $symbol = null): Order
    {
        $order = new Order();

        if ($side)
        {
            $order->side = $side;
            $this->assertAction($order); //TODO:: overriding validation when no side has been set
        }

        if ($symbol)
        {
            $order->symbol = $symbol;
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

    public function stopMarket(string $side, string $symbol, float $quantity, float $stopPrice): Order
    {
        $order = $this->setupOrder($side, $symbol);

        $order->quantity = $quantity;
        $order->stop_price = $stopPrice;
        $order->type = 'STOP_LOSS';

        return $this->newOrder($order);
    }

    public function limit(string $side, string $symbol, float $price, float $quantity): Order
    {
        $order = $this->setupOrder($side, $symbol);

        $order->price = $price;
        $order->quantity = $quantity;
        $order->type = 'LIMIT';

        return $this->newOrder($order);
    }

    public function stopLimit(string $side, string $symbol, float $stopPrice, float $price, float $quantity): Order
    {
        $order = $this->setupOrder($side, $symbol);

        $order->price = $price;
        $order->quantity = $quantity;
        $order->stop_price = $stopPrice;
        $order->type = 'STOP_LOSS_LIMIT';

        return $this->newOrder($order);
    }

    /**
     * @param string $symbol
     *
     * @return Collection<Order>
     */
    abstract public function openOrders(string $symbol): Collection;

    /**
     * @param Order[] $responses
     */
    abstract protected function processOrderResponses(array $responses): Collection;

    /**
     * @param array $exchangeOrderIds
     *
     * @return Collection<Order>
     */
    protected function fetchOrdersWithExchangeIds(array $exchangeOrderIds): Collection
    {
        return Order::query()
            ->whereIn('exchange_order_id', $exchangeOrderIds)
            ->get()
            ->keyBy('exchange_order_id');
    }

    abstract protected function updateOrderDetails(Order $order, array $response): void;
}