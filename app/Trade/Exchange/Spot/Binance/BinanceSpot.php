<?php

namespace App\Trade\Exchange\Spot\Binance;

use App\Models\Order;
use App\Trade\Exchange\AccountBalance;
use App\Trade\Exchange\Asset;
use App\Trade\Exchange\OrderBook;
use ccxt\NetworkError;
use ccxt\OrderNotFound;
use Illuminate\Database\Eloquent\Collection;

trait BinanceSpot
{
    /**
     * @var \ccxt\binance
     */
    protected $api;

    protected static array $limits = [];

    public static function getLimits(): array
    {
        return self::$limits;
    }

    protected function setup(): void
    {
        $this->api = new \ccxt\binance([
            'apiKey'  => $this->apiKey,
            'secret'  => $this->secretKey,
            'options' => [
                'recvWindow' => 5000
            ]
        ]);
    }

    protected function processOrderResponses(array $responses): Collection
    {
        $orders = $this->fetchOrdersWithExchangeIds(
            array_column($responses, 'id'));

        foreach ($responses as $response)
        {
            $order = $orders[$response['id']] ?? $this->setupOrder();

            $this->updateOrderDetails($order, $response);
            $order->save();
        }

        return $orders;
    }

    public function orders(string $symbol): Collection
    {
        return $this->processOrderResponses($this->api->fetch_orders($symbol));
    }

    public function openOrders(?string $symbol = null): Collection
    {
        return $this->processOrderResponses($this->api->fetch_open_orders($symbol));
    }

    public function getAccountBalance(): AccountBalance
    {
        $result = $this->api->fetch_balance();
        $assets = [];

        foreach ($result['total'] as $asset => $total)
        {
            $assets[] = new Asset($asset, $total, $result['free'][$asset]);
        }

        return new AccountBalance($this, $assets);
    }

    public function buildSymbol(string $baseAsset, string $quoteAsset): string
    {
        return "$baseAsset/$quoteAsset";
    }

    public function orderBook(string $symbol): OrderBook
    {
        $orderBook = $this->api->fetch_order_book($symbol);

        return new OrderBook($symbol,
            array_column($orderBook['bids'], 0),
            array_column($orderBook['asks'], 0));
    }

    public function symbols(string $quoteAsset = null): array
    {
        $markets = $this->api->fetch_markets();

        foreach ($markets as $market)
        {
            static::$limits[$market['symbol']] = $market['limits'];
        }

        if ($quoteAsset)
            $markets = array_filter($markets, fn($v) => $v['info']['quoteAsset'] === $quoteAsset);

        return array_column($markets, 'symbol');
    }

    public function candles(string $symbol, string $interval, float $start = null, float $limit = null): array
    {
        return $this->api->fetch_ohlcv($symbol, $interval, $start, $limit);
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
        if ($order->type === 'LIMIT') $order->type = 'LIMIT_MAKER';
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

    public function candleMap(): array
    {
        return [
            'timestamp' => 0,
            'open'      => 1,
            'high'      => 2,
            'low'       => 3,
            'close'     => 4,
            'volume'    => 5,
        ];
    }

    public function minTradeQuantity(string $symbol): float
    {
        if (empty(static::$limits[$symbol]))
        {
            $this->symbols();
        }

        $minQuantity = static::$limits[$symbol]['amount']['min'];
        $minCost = static::$limits[$symbol]['cost']['min'];
        $price = $this->orderBook($symbol)->bestBid();

        return ($quantity = $minCost / $price) < $minQuantity ? $minQuantity : $quantity;
    }

    public function getMaxCandlesPerRequest(): int
    {
        return 1000;
    }
}
