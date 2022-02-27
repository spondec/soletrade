<?php

namespace App\Trade\Exchange\Binance\Spot;

use App\Models\Order;
use App\Trade\Exchange\Exchange;
use ccxt\binance;
use ccxt\NetworkError;
use ccxt\OrderNotFound;
use Illuminate\Database\Eloquent\Collection;

class Orderer extends \App\Trade\Exchange\Orderer
{
    public function __construct(Exchange $exchange, protected binance $api)
    {
        parent::__construct($exchange);
    }

    public function orders(string $symbol): Collection
    {
        return $this->processOrderResponses($this->api->fetch_orders($symbol));
    }

    protected function processOrderResponses(array $responses): Collection
    {
        $orders = $this->fetchOrdersWithExchangeIds(
            \array_column($responses, 'id'));

        foreach ($responses as $response)
        {
            $order = $orders[$response['id']] ?? $this->setupOrder();

            $this->updateOrderDetails($order, $response);
            $order->save();
        }

        return $orders;
    }

    protected function updateOrderDetails(Order $order, array $response): void
    {
        $order->type = $response['info']['type'];
        $order->symbol = $response['symbol'];
        $order->status = $response['status'];
        $order->side = $response['side'];
        $order->stop_price = $response['stopPrice'];
        $order->exchange_order_id = $response['orderId'] ?? $response['id'];
        $order->price = $response['price'];
        $order->quantity = $response['amount'];
        $order->filled = $response['filled'];

        $this->processOrderFills($order, $response);
    }

    protected function processOrderFills(Order $order, array $response): void
    {
        if (isset($response['fills']))
        {
            $commission = 0;

            foreach ($response['fills'] as $fill)
            {
                $commission += $fill['commission'];
            }

            if ($commission)
            {
                $order->commission_asset = $fill[0]['commission_asset'];
            }
        }
    }

    public function openOrders(?string $symbol = null): Collection
    {
        return $this->processOrderResponses($this->api->fetch_open_orders($symbol));
    }

    protected function availableOrderActions(): array
    {
        return ['BUY', 'SELL'];
    }

    /**
     * @throws NetworkError when
     * the order might or might not have been canceled successfully and whether you need to retry or not
     * consecutive calls may hit an already canceled order as well
     * @throws OrderNotFound when
     * canceling an already-closed order
     * canceling an already-canceled order
     */
    protected function executeOrderCancel(Order $order): array
    {
        return $this->api->cancel_order($order->exchange_order_id, $order->symbol);
    }

    protected function handleOrderCancelResponse(Order $order, array $response): void
    {
        $this->updateOrderDetails($order, $response);
    }

    protected function executeNewOrder(Order $order): array
    {
        if ($order->type === 'LIMIT')
        {
            $order->type = 'LIMIT_MAKER';
        }
        return $this->api->create_order($order->symbol,
            $order->type,
            $order->side,
            $order->quantity,
            $order->price, ['stopPrice' => $order->stop_price]);
    }

    protected function handleNewOrderResponse(Order $order, array $response): void
    {
        $this->updateOrderDetails($order, $response);
    }

    protected function executeOrderUpdate(Order $order): array
    {
        return $this->api->fetch_order($order->exchange_order_id, $order->symbol);
    }

    protected function handleOrderUpdateResponse(Order $order, array $response): void
    {
        $this->updateOrderDetails($order, $response);
    }
}