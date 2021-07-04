<?php

namespace App\Trade\Exchange;

use App\Models\Order;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

abstract class AbstractExchange
{
    const ROOT_URI = null;
    const NEW_ORDER_URL = null;
    const ORDER_UPDATE_URL = null;

    protected string $apiKey;
    protected string $secretKey;

    protected static ?AbstractExchange $instance = null;

    protected string $name;

    protected array $actions;
    protected string $account;

    /**
     * @return string[]
     */
    abstract protected function availableOrderActions(): array;

    private function __construct()
    {
//        if (empty(static::ROOT_URI) || empty(static::NEW_ORDER_URL))
//        {
//            throw new \InvalidArgumentException('Exchange constants are not properly defined.');
//        }

        $this->name = class_basename(static::class);

        $config = Config::get('exchange.' . mb_strtoupper($this->name));

        if (empty($config['apiKey']) || empty($config['secretKey']))
        {
            throw new \LogicException('API/Secret key for exchange ' . $this->name . ' could not found.');
        }

        $this->apiKey = $config['apiKey'];
        $this->secretKey = $config['secretKey'];

        $this->actions = $this->availableOrderActions();
        $this->account = $this->accountType();
    }

    public function price(string $symbol): float
    {

    }

    abstract protected function bestSellPrice(): float;

    abstract protected function bestBuyPrice(): float;

    abstract public function convert(string $from, string $to, float $quantity): float;

    public final function syncOrder(Order $order): void
    {
        $request = $this->prepareOrderUpdateRequest($order);
        $response = $this->httpRequest('post', static::ORDER_UPDATE_URL, $request->data());

        $this->handleOrderUpdateResponse($order, $response);
    }

    public final function cancelOrder(Order $order): bool
    {

    }

    protected function setupOrder(string $side, string $symbol): Order
    {
        $order = new Order();

        $order->side = $side;
        $order->symbol = $symbol;

        $order->exchange = $this->name;
        $order->account = $this->account;

        if (!in_array($order->side, $this->actions))
        {
            throw new \UnexpectedValueException("$this->name exchange doesn't allow to $order->side.\n
            Available actions: " . implode(', ', $this->actions));
        }

        return $order;
    }

    public function market(string $side, string $symbol, float $quantity): Order
    {
        $order = $this->setupOrder($side, $symbol);

        $order->account =
        $order->quantity = $quantity;
        $order->type = 'MARKET';

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
        $order->type = 'STOP_LIMIT';

        return $this->newOrder($order);
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

    protected function httpRequest(string $method, string $url, array $data): array
    {
        /** @var Response $response */
        $response = call_user_func([Http::class, $method], $url,
            array_merge($data, $this->getDefaultRequestData()));

        return $response->json() ?? throw new \HttpResponseException(
                "Empty result received from request $method:$url.");
    }

    protected function hmacSha256(string $key, array $data): string
    {
        return hash_hmac('sha256', http_build_query($data), $key);
    }

    protected function newOrder(Order $order): Order
    {
        $request = $this->prepareNewOrderRequest($order);
        $order->logRequest('newOrder', $request);

        $response = $this->httpRequest('post', self::NEW_ORDER_URL, $request);
        $order->logResponse('newOrder', $response);

        $this->handleNewOrderResponse($order, $response);

        return $order;
    }

    abstract protected function prepareCancelOrderRequest(Order $order): array;

    abstract protected function handleCancelOrderResponse(Order $order, array $response): void;

    abstract protected function prepareNewOrderRequest(Order $order): array;

    abstract protected function handleNewOrderResponse(Order $order, array $response): void;

    abstract protected function prepareOrderUpdateRequest(Order $order): array;

    abstract protected function handleOrderUpdateResponse(Order $order, array $response): void;

    abstract protected function getDefaultRequestData(): array;

    abstract protected function accountType(): string;

    public static function instance(): static
    {
        if (!static::$instance)
        {
            return static::$instance = new static();
        }

        return static::$instance;
    }
}