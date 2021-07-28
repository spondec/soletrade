<?php

namespace App\Trade\Exchange;

use App\Models\Exchange;
use App\Models\Order;
use App\Trade\CandleMap;
use App\Trade\Scanner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class AbstractExchange
{
    protected string $apiKey;
    protected string $secretKey;

    protected $api;

    protected static ?AbstractExchange $instance = null;

    protected string $name;

    protected Exchange $model;

    protected array $actions;
    protected string $account;

    protected ?AccountBalance $prevBalance = null;
    protected AccountBalance $currentBalance;

    protected Scanner $scanner;

    protected final function register(): int
    {
        DB::table('exchanges')->insertOrIgnore([
            'class'   => static::class,
            'name'    => $this->name,
            'account' => $this->account
        ]);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->model = Exchange::query()
            ->where('class', static::class)
            ->limit(1)
            ->firstOrFail();

        return $this->model->id;
    }

    public function scanner(): Scanner
    {
        return $this->scanner;
    }

    public function id(): int
    {
        return $this->model->id;
    }

    /**
     * @return string[]
     */
    abstract protected function availableOrderActions(): array;

    abstract public function getMaxCandlesPerRequest(): int;

    private function __construct()
    {
        $this->name = mb_strtoupper(class_basename(static::class));

        $config = Config::get('exchange.' . $this->name);

        $this->apiKey = $config['apiKey'] ?? null;
        $this->secretKey = $config['secretKey'] ?? null;

        $this->actions = $this->availableOrderActions();
        $this->account = $this->accountType();

        $this->setup();
        $this->register();

        $this->scanner = new Scanner($this);
    }

    protected function setup(): void
    {

    }

    public function getApi(): mixed
    {
        return $this->api;
    }

    public function info(): array
    {
        return [
            'name'    => $this->name,
            'account' => $this->account,
            'actions' => implode(', ', $this->actions),
        ];
    }

    public function name(): string
    {
        return $this->name;
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

        $order->quantity = $quantity;
        $order->type = 'MARKET';

        return $this->newOrder($order);
    }

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

    abstract public function getAccountBalance(): AccountBalance;

    public function accountBalance(): AccountBalance
    {
        $balance = $this->getAccountBalance();

        if (!$this->prevBalance)
        {
            $this->prevBalance = $balance;
        }

        return $this->currentBalance = $balance;
    }

    public function roe(): ?array
    {
        if (!$this->prevBalance) return null;

        return $this->accountBalance()->calculateRoe($this->prevBalance);
    }

    abstract public function orderBook(string $symbol): OrderBook;

    /**
     * @return string[]
     */
    abstract public function symbols(string $quoteAsset = null): array;

    abstract public function buildSymbol(string $baseAsset, string $quoteAsset): ?string;

    abstract public function minTradeQuantity(string $symbol): float;

    abstract public function candleMap(): CandleMap;

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