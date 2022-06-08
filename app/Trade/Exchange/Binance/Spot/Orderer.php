<?php

namespace App\Trade\Exchange\Binance\Spot;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Exchange\Exchange;
use ccxt\binance;

class Orderer extends \App\Trade\Exchange\Orderer
{
    public function __construct(Exchange $exchange, protected binance $api)
    {
        parent::__construct($exchange);
    }

    protected function processOrderDetails(Order $order, array $response): void
    {
        $order->type = $response['info']['type'];
        $order->symbol = $response['symbol'];
        $order->status = $response['status'];
        $order->side = $response['side'];
        $order->stop_price = $response['stopPrice'];
        $order->exchange_order_id = $response['orderId'] ?? $response['id'];
        $order->price = $response['price'];
        $order->quantity = $response['amount'];
        $order->filled = $response['filled'] ?? 0;
    }

    protected function executeOrderCancel(Order $order): array
    {
        return $this->api->cancel_order($order->exchange_order_id, $order->symbol);
    }

    protected function executeNewOrder(Order $order): array
    {
        return $this->api->create_order(
            $order->symbol,
            $order->type,
            $order->side,
            $order->quantity,
            $order->price,
            ['stopPrice' => $order->stop_price]
        );
    }

    protected function executeOrderUpdate(Order $order): array
    {
        return $this->api->fetch_order($order->exchange_order_id, $order->symbol);
    }

    /**
     * @param Order $order
     * @param array $response
     *
     * @return Fill[]
     */
    protected function processOrderFills(Order $order, array $response): array
    {
        $fills = [];
        foreach ($response['fills'] ?? [] as $fill) {
            $fills[] = $new = new Fill();

            $new->price = $fill['price'];
            $new->quantity = $fill['qty'];
            $new->commission = $fill['commission'];
            $new->commission_asset = $fill['commissionAsset'];
            $new->trade_id = $fill['tradeId'];
        }

        return $fills;
    }
}
