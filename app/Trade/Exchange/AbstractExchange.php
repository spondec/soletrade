<?php

namespace App\Trade\Exchange;

use App\Models\Order;
use App\Trade\Exchange\Request\NewOrderRequest;
use App\Trade\Exchange\Request\OrderUpdateRequest;
use App\Trade\Exchange\Response\NewOrderResponse;
use App\Trade\Exchange\Response\OrderUpdateResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

abstract class AbstractExchange
{
    const NAME = null;

    const ROOT_URI = null;
    const NEW_ORDER_URL = null;
    const ORDER_UPDATE_URL = null;

    /**
     * Current order.
     */
    protected ?Order $order = null;

    protected string $apiKey;
    protected string $secretKey;

    protected static ?AbstractExchange $instance = null;
    protected string $action;

    private function __construct()
    {
        if (empty(static::NAME) || empty(static::ROOT_URI) || empty(static::NEW_ORDER_URL))
        {
            throw new \InvalidArgumentException('Exchange constants are not properly defined.');
        }

        $config = Config::get('exchange.' . mb_strtoupper(static::NAME));

        if (empty($config['apiKey']) || empty($config['secretKey']))
        {
            throw new \LogicException('API/Secret key for exchange ' . static::NAME . ' could not found.');
        }

        $this->apiKey = $config['apiKey'];
        $this->secretKey = $config['secretKey'];
    }

    abstract protected function bestSellPrice(): float;

    abstract protected function bestBuyPrice(): float;

    abstract public function convert(string $from, string $to, float $quantity): float;

    public final function updateOrder(Order $order): void
    {
        $request = $this->prepareOrderUpdateRequest($order);
        $response = $this->httpRequest('post', static::ORDER_UPDATE_URL, $request->data());

        $this->prepareOrderUpdateResponse($response)->updateOrderState($order);
    }

    public final function sendOrder(): Order
    {
        $request = $this->prepareNewOrderRequest();
        $data = $request->data();

        $response = $this->httpRequest('post', static::NEW_ORDER_URL, $data);

        if ($this->order && empty($this->order->request))
        {
            $this->order->request = $data;
        }

        $newOrderResponse = $this->prepareNewOrderResponse($response);
        $newOrderResponse->updateOrderState($this->order);

        return $this->detachOrder();
    }

    public function market(string $symbol, float $size): static
    {
        $this->order->symbol = $symbol;
        $this->order->quantity = $size;
        $this->order->type = 'MARKET';

        return $this;
    }

    public function limit(string $symbol, float $price, float $size): static
    {
        $this->order->symbol = $symbol;
        $this->order->price = $price;
        $this->order->quantity = $size;
        $this->order->type = 'LIMIT';

        return $this;
    }

    public function stopLimit(string $symbol,
                              float  $stopPrice,
                              float  $price,
                              float  $size): static
    {
        $this->order->symbol = $symbol;
        $this->order->price = $price;
        $this->order->quantity = $size;
        $this->order->stop_price = $stopPrice;
        $this->order->type = 'STOP_LIMIT';

        return $this;
    }

    /**
     * @return Order[]
     */
    abstract public function openOrders(): array;

    abstract public function accountBalance(): AccountBalance;

    abstract public function orderBook(string $symbol): OrderBook;

    /**
     * @return string[]
     */
    abstract public function symbolList(): array;

    abstract public function candleMap(): array;

    abstract public function candles(string $symbol, string $interval, float $start, float $end): array;

    abstract protected function prepareNewOrderRequest(): NewOrderRequest;

    abstract protected function prepareOrderUpdateRequest(Order $order): OrderUpdateRequest;

    abstract protected function prepareNewOrderResponse(array $rawResponse): NewOrderResponse;

    abstract protected function prepareOrderUpdateResponse(array $rawResponse): OrderUpdateResponse;

    protected function getDefaultRequestData(): array
    {
        return [
            'apiKey' => $this->apiKey,
            'secretKey' => $this->secretKey,
        ];
    }

    protected final function httpRequest(string $method, string $url, array $data): ?array
    {
        /** @var Response $response */
        $response = call_user_func([Http::class, $method],
            $url, array_merge($data, $this->getDefaultRequestData()));

        return $response->json() ?? throw new \HttpResponseException(
                "Empty result received from request $method:$url.");
    }

    protected final function detachOrder(): Order
    {
        $order = $this->order;
        $this->order = null;
        $order->save();

        return $order;
    }

    public static function instance(): static
    {
        if (!static::$instance)
        {
            return static::$instance = new static();
        }

        return static::$instance;
    }

    public function sell(): static
    {
        $this->order->side = 'SELL';
        return $this;
    }

    public function buy(): static
    {
        $this->order->side = 'BUY';
        return $this;
    }
}