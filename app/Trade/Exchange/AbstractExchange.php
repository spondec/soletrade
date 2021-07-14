<?php

namespace App\Trade\Exchange;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;

abstract class AbstractExchange
{
    protected string $apiKey;
    protected string $secretKey;

    protected $api;

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
        $this->name = mb_strtoupper(class_basename(static::class));

        $config = Config::get('exchange.' . $this->name);

        if (empty($config['apiKey']) || empty($config['secretKey']))
        {
            throw new \LogicException('API/Secret key for exchange ' . $this->name . ' could not found.');
        }

        $this->apiKey = $config['apiKey'];
        $this->secretKey = $config['secretKey'];

        $this->actions = $this->availableOrderActions();
        $this->account = $this->accountType();

        $this->setup();
    }

    protected function setup(): void
    {

    }

    public function getApi(): mixed
    {
        return $this->api;
    }

    /**
     * @param Order[] $responses
     */
    abstract protected function processOrderResponses(array $responses): Collection;

    public final function syncOrder(Order $order): Order
    {
        $response = $this->executeOrderUpdate($order);
        $this->handleOrderUpdateResponse($order, $response);

        $order->logResponse('update', $response);
        $order->save();

        return $order;
    }

    public final function cancelOrder(Order $order): Order
    {
        $response = $this->executeOrderCancel($order);
        $this->handleOrderCancelResponse($order, $response);

        $order->logResponse('cancel', $response);
        $order->save();

        return $order;
    }

    protected function setupOrder(string $side = null, string $symbol = null): Order
    {
        $order = new Order();

        if ($side)
        {
            $order->side = $side;
            $this->assertAction($order); //TODO:: overriding validation when no side has been set
        }

        if ($symbol) $order->symbol = $symbol;

        $order->exchange = $this->name;
        $order->account = $this->account;

        return $order;
    }

    protected final function assertAction(Order $order): void
    {
        if (!in_array($order->side, $this->actions))
        {
            throw new \UnexpectedValueException("$this->name doesn't allow to $order->side.\n
            Available actions: " . implode(', ', $this->actions));
        }
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
     * @param array $exchangeOrderIds
     *
     * @return Collection|Order[]
     */
    protected function fetchOrdersWithExchangeIds(array $exchangeOrderIds): Collection
    {
        return Order::query()
            ->whereIn('exchange_order_id', $exchangeOrderIds)
            ->get()
            ->keyBy('exchange_order_id');
    }

    /**
     * @return Order[]
     */
    abstract public function openOrders(string $symbol): \Illuminate\Database\Eloquent\Collection;

    abstract public function accountBalance(): AccountBalance;

    abstract public function orderBook(string $symbol): OrderBook;

    /**
     * @return string[]
     */
    abstract public function symbolList(string $quoteAsset = null): array;

    abstract public function buildSymbol(string $baseAsset, string $quoteAsset): ?string;

    abstract public function minTradeQuantity(string $symbol): float;

    abstract public function candleMap(): array;

    abstract public function candles(string $symbol, string $interval, float $start = null, float $limit = null): array;

    protected function newOrder(Order $order): Order
    {
        $response = $this->executeNewOrder($order);
        $this->handleNewOrderResponse($order, $response);

        $order->logResponse('new', $response);
        $order->save();

        return $order;
    }

    abstract protected function updateOrderDetails(Order $order, array $response): void;

    abstract protected function executeOrderCancel(Order $order): array;

    abstract protected function handleOrderCancelResponse(Order $order, array $response): void;

    abstract protected function executeNewOrder(Order $order): array;

    abstract protected function handleNewOrderResponse(Order $order, array $response): void;

    abstract protected function executeOrderUpdate(Order $order): array;

    abstract protected function handleOrderUpdateResponse(Order $order, array $response): void;

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