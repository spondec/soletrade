<?php

namespace App\Trade\Exchange\FTX;

use App\Models\Fill;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderType;
use App\Trade\Enum;
use App\Trade\Exchange\Exchange;

class Orderer extends \App\Trade\Exchange\Orderer
{
    public function __construct(Exchange $exchange, protected \ccxt\ftx $api)
    {
        parent::__construct($exchange);
    }

    /**
     */
    protected function executeOrderCancel(Order $order): array
    {
        $parsedType = $this->parseOrderType($order->type);

        if ($order->type === OrderType::STOP_LIMIT)
        {
            $response = $this->api->cancel_order($order->exchange_order_id, params: ['type' => $parsedType]);
        }
        else
        {
            $response = $this->api->cancel_order($order->exchange_order_id);
        }

        if ($response === 'Order cancelled' || $response === 'Order queued for cancellation')
        {
            return $this->executeOrderUpdate($order);
        }

        throw new \LogicException('Unexpected order cancel response: ' . is_array($response) ? json_encode($response) : $response);
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

    protected function executeOrderUpdate(Order $order): array
    {
        $parsedType = $this->parseOrderType($order->type);
        if ($order->type === OrderType::STOP_LIMIT)
        {
            $conditionalOrders = $this->api->fetch_orders($order->symbol, params: ['type' => $parsedType]);

            $response = array_filter($conditionalOrders, static function ($conditionalOrder) use ($order) {
                return $conditionalOrder['id'] == $order->exchange_order_id;
            });

            if (!$response)
            {
                throw new \LogicException('Order not found for ID: ' . $order->id);
            }

            if (count($response) > 1)
            {
                throw new \LogicException('Multiple conditional orders found for order ID: ' . $order->id);
            }

            return reset($response);
        }
        return $this->api->fetch_order($order->exchange_order_id, params: ['type' => $parsedType]);
    }

    /**
     * @throws \ccxt\ArgumentsRequired
     * @throws \ccxt\InvalidOrder
     */
    protected function executeNewOrder(Order $order): array
    {
        return $this->api->create_order($order->symbol,
            $this->parseOrderType($order->type),
            strtolower(Enum::case($order->side)),
            $order->quantity,
            $order->price, [
                'stopPrice'  => $order->stop_price,
                'reduceOnly' => $order->reduce_only
            ]);
    }

    protected function parseOrderStatus(OrderStatus $status): string
    {
        $value = Enum::case($status);
        return $this->orderStatusMap()[$value]
            ?? throw new \LogicException('Unsupported order status: ' . $value);
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

    protected function processOrderDetails(Order $order, array $response): void
    {
        $order->type = $this->parseOrderTypeString($response['type']);
        $order->symbol = $response['info']['market'];
        $order->status = $this->parseOrderStatusString($response['status']);
        $order->side = strtoupper($response['side']);
        $order->stop_price = $response['info']['triggerPrice'] ?? null;
        $order->exchange_order_id = $response['id'];
        $order->price = $response['info']['orderPrice'] ?? $response['price'];
        $order->quantity = $response['amount'];
        $order->filled = $response['filled'] ?? 0;
    }

    protected function parseOrderTypeString(string $type): OrderType
    {
        $map = $this->orderTypeMap();
        $key = array_search($type, $map);
        return OrderType::from($key)
            ?? throw new \LogicException('Unsupported order type: ' . $type);
    }

    protected function parseOrderStatusString(string $status): OrderStatus
    {
        $map = $this->orderStatusMap();

        foreach ($map as $enum => $value)
        {
            if (is_array($value))
            {
                if (in_array($status, $value))
                {
                    return OrderStatus::from($enum);
                }
            }
            else if ($value == $status)
            {
                return OrderStatus::from($enum);
            }
        }

        throw new \LogicException('Unsupported order status: ' . $status);
    }

    protected function processOrderFills(Order $order, array $response): array
    {
        $fills = [];

        $trades = $this->api->fetch_order_trades($order->exchange_order_id);
        foreach ($trades as $fill)
        {
            $fills[] = $new = new Fill();

            $new->timestamp = $fill['timestamp'];
            $new->price = $fill['price'];
            $new->quantity = $fill['amount'];
            $new->commission = $fill['fee']['cost'];
            $new->commission_asset = $fill['fee']['currency'];
            $new->trade_id = $fill['id'];
        }

        return $fills;
    }
}