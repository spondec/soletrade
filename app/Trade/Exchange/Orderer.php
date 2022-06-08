<?php

namespace App\Trade\Exchange;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Enum;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Exception\FailedOrderFillException;
use Illuminate\Support\Collection;

abstract class Orderer implements \App\Trade\Contract\Exchange\Orderer
{
    public function __construct(protected Exchange $exchange)
    {
    }

    /**
     * @param Order $order
     *
     * @return Fill[]
     */
    public function sync(Order $order): array
    {
        $response = $this->executeOrderUpdate($order);
        return $this->handleOrderResponse($order, $response, 'sync');
    }

    /**
     * @param Order  $order
     * @param array  $response
     * @param string $responseType
     *
     * @return Fill[]
     */
    private function handleOrderResponse(Order $order, array $response, string $responseType): array
    {
        $this->processOrderDetails($order, $response);

        $fills = new Collection($this->processOrderFills($order, $response));

        if ($filled = $order->filled) {
            if (!$fills->count()) {
                throw new FailedOrderFillException('Failed to process order fills.');
            }

            if ($filled != $fills->sum('quantity')) {
                throw new FailedOrderFillException('Filled quantity does not match.');
            }
        }

        $order->logResponse($responseType, $response);

        if (!$order->save()) {
            throw new \UnexpectedValueException('Failed to save order.');
        }

        return $fills->map(static function (Fill $fill) use ($order) {
            $fill->order()->associate($order);
            return $fill->firstUniqueOrCreate();
        })->all();
    }

    abstract protected function executeOrderUpdate(Order $order): array;

    public function cancel(Order $order): Order
    {
        $response = $this->executeOrderCancel($order);
        $this->handleOrderResponse($order, $response, 'cancel');

        return $order;
    }

    abstract protected function executeOrderCancel(Order $order): array;

    public function market(Side $side, string $symbol, float $quantity, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->quantity = $quantity;
        $order->type = OrderType::MARKET;

        return $this->newOrder($order);
    }

    protected function setupOrder(Side $side = null, string $symbol = null, bool $reduceOnly = null): Order
    {
        $order = new Order();

        if ($side !== null) {
            $order->side = Enum::case($side);
        }

        if ($symbol !== null) {
            $order->symbol = $symbol;
        }

        if ($reduceOnly !== null) {
            $order->reduce_only = $reduceOnly;
        }

        $order->exchange_id = $this->exchange->model()->id;

        return $order;
    }

    protected function newOrder(Order $order): Order
    {
        $response = $this->executeNewOrder($order);
        $this->handleOrderResponse($order, $response, 'new');

        return $order;
    }

    abstract protected function executeNewOrder(Order $order): array;

    public function stopMarket(Side $side, string $symbol, float $quantity, float $stopPrice, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->quantity = $quantity;
        $order->stop_price = $stopPrice;
        $order->type = OrderType::STOP_MARKET;

        return $this->newOrder($order);
    }

    public function limit(Side $side, string $symbol, float $price, float $quantity, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->price = $price;
        $order->quantity = $quantity;
        $order->type = OrderType::LIMIT;

        return $this->newOrder($order);
    }

    public function stopLimit(Side $side, string $symbol, float $stopPrice, float $price, float $quantity, bool $reduceOnly): Order
    {
        $order = $this->setupOrder($side, $symbol, $reduceOnly);

        $order->price = $price;
        $order->quantity = $quantity;
        $order->stop_price = $stopPrice;
        $order->type = OrderType::STOP_LIMIT;

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
