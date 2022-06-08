<?php

namespace App\Trade\Exchange\FTX;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Enum;
use App\Trade\Enum\OrderStatus;
use App\Trade\Enum\OrderType;
use App\Trade\Exception\OrderFilledInCancelRequest;
use App\Trade\Exception\OrderNotCanceledException;
use App\Trade\Exchange\Exchange;
use App\Trade\Process\RecoverableRequest;
use ccxt\InvalidOrder;

class Orderer extends \App\Trade\Exchange\Orderer
{
    protected const CONDITIONAL_ORDER_TYPES = [
        OrderType::STOP_LIMIT
    ];

    public function __construct(Exchange $exchange, protected \ccxt\ftx $api)
    {
        parent::__construct($exchange);
    }

    public function api(): \ccxt\ftx
    {
        return $this->api;
    }

    protected function executeOrderCancel(Order $order): array
    {
        $parsedType = $this->parseOrderType($order->type);

        try {
            $response = $this->isConditional($order)
                ? $this->sendConditionalOrderCancelRequest($order, $parsedType)
                : $this->sendOrderCancelRequest($order);
        } catch (InvalidOrder $e) {
            if (\str_contains($e->getMessage(), 'Order already closed')) {
                $this->sync($order); //to register fills

                if ($order->filled) {
                    throw new OrderFilledInCancelRequest($e->getMessage());
                }
            }

            throw $e;
        }

        if ($response === 'Order cancelled') {
            return $this->executeOrderUpdate($order);
        }

        if ($response === 'Order queued for cancellation') {
            return $this->handleOrderQueuedForCancellation($order);
        }

        throw new \LogicException('Unexpected order cancel response: ' . $response);
    }

    protected function parseOrderType(OrderType $type): string
    {
        $value = Enum::case($type);
        return $this->orderTypeMap()[$value]
            ?? throw new \LogicException('Unsupported order type: ' . $value);
    }

    protected function orderTypeMap(): array
    {
        return [
            Enum::case(OrderType::LIMIT)      => 'limit',
            Enum::case(OrderType::STOP_LIMIT) => 'stop',
            Enum::case(OrderType::MARKET)     => 'market'
        ];
    }

    protected function isConditional(Order $order): bool
    {
        return \in_array($order->type, static::CONDITIONAL_ORDER_TYPES);
    }

    private function sendConditionalOrderCancelRequest(Order $order, string $parsedType): string
    {
        return RecoverableRequest::new(
            fn () => $this->api->cancel_order($order->exchange_order_id, params: ['type' => $parsedType])
        )->run();
    }

    private function sendOrderCancelRequest(Order $order): string
    {
        return RecoverableRequest::new(
            fn () => $this->api->cancel_order($order->exchange_order_id)
        )->run();
    }

    protected function executeOrderUpdate(Order $order): array
    {
        $parsedType = $this->parseOrderType($order->type);

        if ($this->isConditional($order)) {
            return $this->sendConditionalOrderUpdateRequest($order, $parsedType);
        }

        return $this->sendOrderUpdateRequest($order, $parsedType);
    }

    private function sendConditionalOrderUpdateRequest(Order $order, string $parsedType): array
    {
        $this->assertConditional($order);

        $conditionals = RecoverableRequest::new(
            fn () => $this->api->fetch_orders($order->symbol, params: ['type' => $parsedType])
        )->run();

        $responses = \array_filter($conditionals, static function (array $conditional) use ($order) {
            return $conditional['id'] == $order->exchange_order_id;
        });

        return $this->assertSingleOrder($responses, $order);
    }

    protected function assertConditional(Order $order): void
    {
        if (!$this->isConditional($order)) {
            throw new \LogicException('$order expected to be conditional. Order ID: ' . $order->id);
        }
    }

    protected function assertSingleOrder(array $responses, Order $order): array
    {
        if (!$responses) {
            throw new \LogicException('Order not found for ID: ' . $order->id);
        }

        if (\count($responses) > 1) {
            throw new \LogicException('Multiple orders found for order ID: ' . $order->id);
        }

        return \reset($responses);
    }

    private function sendOrderUpdateRequest(Order $order, string $parsedType): array
    {
        return RecoverableRequest::new(
            fn () => $this->api->fetch_order($order->exchange_order_id, params: ['type' => $parsedType])
        )->run();
    }

    /**
     *
     * Sends order update request until cancellation and returns raw order update response on success.
     *
     * @param Order $order
     *
     * @return array
     * @throws OrderNotCanceledException
     */
    private function handleOrderQueuedForCancellation(Order $order): array
    {
        return RecoverableRequest::new(function () use ($order) {
            $response = $this->executeOrderUpdate($order);
            $this->processOrderDetails($order, $response);

            if ($order->isOpen()) {
                throw new OrderNotCanceledException("Order queued for cancellation but unable to cancel. Order ID: " . $order->id);
            }

            return $response;
        }, handle: [OrderNotCanceledException::class])->run();
    }

    protected function processOrderDetails(Order $order, array $response): void
    {
        $order->type = $this->parseOrderTypeString($response['type']);
        $order->symbol = $response['info']['market'];
        $order->status = $this->parseOrderStatusString($response['status']);
        $order->side = \strtoupper($response['side']);
        $order->stop_price = $response['info']['triggerPrice'] ?? null;
        $order->exchange_order_id = $response['id'];
        $order->price = $response['info']['orderPrice'] ?? $response['price'];
        $order->quantity = $response['amount'];
        $order->filled = $response['filled'] ?? 0;
    }

    protected function parseOrderTypeString(string $type): OrderType
    {
        $map = $this->orderTypeMap();
        $key = \array_search($type, $map);
        return OrderType::from($key)
            ?? throw new \LogicException('Unsupported order type: ' . $type);
    }

    protected function parseOrderStatusString(string $status): OrderStatus
    {
        $map = $this->orderStatusMap();

        foreach ($map as $enum => $value) {
            if (\is_array($value)) {
                if (\in_array($status, $value)) {
                    return OrderStatus::from($enum);
                }
            } elseif ($value == $status) {
                return OrderStatus::from($enum);
            }
        }

        throw new \LogicException('Unsupported order status: ' . $status);
    }

    protected function orderStatusMap(): array
    {
        return [
            Enum::case(OrderStatus::OPEN)     => 'open',
            Enum::case(OrderStatus::NEW)      => 'new',
            Enum::case(OrderStatus::CLOSED)   => 'closed',
            Enum::case(OrderStatus::CANCELED) => ['canceled', 'cancelled'],
        ];
    }

    protected function executeNewOrder(Order $order): array
    {
        return $this->sendNewOrderRequest($order);
    }

    private function sendNewOrderRequest(Order $order): array
    {
        return RecoverableRequest::new(
            fn () => $this->api->create_order(
                $order->symbol,
                $this->parseOrderType($order->type),
                \strtolower(Enum::case($order->side)),
                $order->quantity,
                $order->price,
                [
                    'stopPrice'  => $order->stop_price,
                    'reduceOnly' => $order->reduce_only
                ]
            )
        )->run();
    }

    protected function parseOrderStatus(OrderStatus $status): string
    {
        $value = Enum::case($status);
        return $this->orderStatusMap()[$value]
            ?? throw new \LogicException('Unsupported order status: ' . $value);
    }

    private function isOrderConditionFulfilled(Order $order, array $response): bool
    {
        $this->assertConditional($order);

        if ($order->type === OrderType::STOP_LIMIT) {
            return $response['info']['status'] === 'triggered';
        }

        throw new \LogicException('Unhandled conditional order type: ' . Enum::case($order->type));
    }

    protected function processOrderFills(Order $order, array $response): array
    {
        $fills = [];

        if ($this->isConditional($order) && $order->exists) {
            if (!$this->isOrderConditionFulfilled($order, $response)) {
                return [];
            }
            $orders = RecoverableRequest::new(
                fn () => $this->api->fetch_orders($order->symbol)
            )->run();

            $responses = $this->filterConditionalOrderMatch($order, $orders);
            $match = $this->assertSingleOrder($responses, $order);
        }

        $trades = RecoverableRequest::new(
            fn () => $this->api->fetch_order_trades($match['id'] ?? $order->exchange_order_id)
        )->run();

        $order->logResponse('fills', $trades);

        $filled = 0;
        foreach ($trades as $fill) {
            $fills[] = $new = new Fill();

            $new->timestamp = $fill['timestamp'];
            $new->price = $fill['price'];
            $filled += $new->quantity = $fill['amount'];
            $new->commission = $fill['fee']['cost'];
            $new->commission_asset = $fill['fee']['currency'];
            $new->trade_id = $fill['id'];
        }

        $order->filled = $filled;
        return $fills;
    }

    private function filterConditionalOrderMatch(Order $order, array $orders): array
    {
        if (!$orderResponses = $order->responses) {
            throw new \LogicException('No order response found. Order ID: ' . $order->id);
        }

        $conditionalResponse = $orderResponses['update'] ?? $orderResponses['new'] ?? null;

        if (!$conditionalResponse) {
            throw new \LogicException('No conditional response found. Order ID: ' . $order->id);
        }

        $conditionalResponse = \end($conditionalResponse);

        return \array_filter($orders, static function (array $orderResponse) use ($conditionalResponse) {
            if ($orderResponse['timestamp'] < $conditionalResponse['timestamp']) {
                return false;
            }

            if ($orderResponse['side'] != $conditionalResponse['side']) {
                return false;
            }

            if ($orderResponse['amount'] != $conditionalResponse['amount']) {
                return false;
            }

            if ($orderResponse['info']['reduceOnly'] != $conditionalResponse['info']['reduceOnly']) {
                return false;
            }

            if ($orderResponse['type'] != $conditionalResponse['info']['orderType']) {
                return false;
            }

            return true;
        });
    }
}
