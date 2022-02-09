<?php

namespace App\Trade\Exchange;

use App\Models\Exchange as ExchangeModel;
use App\Models\Order;
use App\Trade\CandleMap;
use App\Trade\HasName;
use App\Trade\CandleUpdater;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class Exchange
{
    use HasName;

    protected static ?Exchange $instance = null;
    protected ?string $apiKey;
    protected ?string $secretKey;
    protected mixed $api;
    protected ExchangeModel $exchange;

    protected array $actions;
    protected string $account;

    protected ?AccountBalance $prevBalance = null;
    protected AccountBalance $currentBalance;

    protected CandleUpdater $updater;

    private function __construct()
    {
        $config = Config::get('trade.exchanges.' . static::name());

        if (!$config)
        {
            throw new \InvalidArgumentException('Invalid config for ' . static::name());
        }

        $this->apiKey = $config['apiKey'] ?? null;
        $this->secretKey = $config['secretKey'] ?? null;

        $this->actions = $this->availableOrderActions();
        $this->account = $this->accountType();

        $this->setup();
        $this->register();

        $this->updater = App::make(CandleUpdater::class, ['exchange' => $this]);
    }

    /**
     * @return string[]
     */
    abstract protected function availableOrderActions(): array;

    abstract protected function accountType(): string;

    protected function setup(): void
    {

    }

    protected function register(): void
    {
        DB::table('exchanges')->insertOrIgnore([
            'class'   => static::class,
            'name'    => static::name(),
            'account' => $this->account
        ]);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->exchange = ExchangeModel::query()
            ->where('class', static::class)
            ->limit(1)
            ->firstOrFail();
    }

    public static function instance(): static
    {
        if (!static::$instance)
        {
            return static::$instance = new static();
        }

        return static::$instance;
    }

    public function updater(): CandleUpdater
    {
        return $this->updater;
    }

    abstract public function getMaxCandlesPerRequest(): int;

    public function getApi(): mixed
    {
        return $this->api;
    }

    public function info(): array
    {
        return [
            'name'    => static::name(),
            'account' => $this->account,
            'actions' => \implode(', ', $this->actions),
        ];
    }

    public final function syncOrder(Order $order): Order
    {
        $response = $this->executeOrderUpdate($order);
        $this->handleOrderUpdateResponse($order, $response);

        $order->logResponse('update', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeOrderUpdate(Order $order): array;

    abstract protected function handleOrderUpdateResponse(Order $order, array $response): void;

    public final function cancelOrder(Order $order): Order
    {
        $response = $this->executeOrderCancel($order);
        $this->handleOrderCancelResponse($order, $response);

        $order->logResponse('cancel', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeOrderCancel(Order $order): array;

    abstract protected function handleOrderCancelResponse(Order $order, array $response): void;

    public function market(string $side, string $symbol, float $quantity): Order
    {
        $order = $this->setupOrder($side, $symbol);

        $order->quantity = $quantity;
        $order->type = 'MARKET';

        return $this->newOrder($order);
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

        $order->exchange_id = $this->id();

        return $order;
    }

    protected final function assertAction(Order $order): void
    {
        if (!\in_array($order->side, $this->actions))
        {
            throw new \UnexpectedValueException(static::name() . " doesn't allow to take action: $order->side.\n
            Available actions: " . \implode(', ', $this->actions));
        }
    }

    public function id(): int
    {
        return $this->exchange->id;
    }

    protected function newOrder(Order $order): Order
    {
        $response = $this->executeNewOrder($order);
        $this->handleNewOrderResponse($order, $response);

        $order->logResponse('new', $response);
        $order->save();

        return $order;
    }

    abstract protected function executeNewOrder(Order $order): array;

    abstract protected function handleNewOrderResponse(Order $order, array $response): void;

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
     * @return Order[]
     */
    abstract public function openOrders(string $symbol): \Illuminate\Database\Eloquent\Collection;

    public function roe(): ?array
    {
        if (!$this->prevBalance) return null;

        return $this->accountBalance()->calculateRoe($this->prevBalance);
    }

    public function accountBalance(): AccountBalance
    {
        $balance = $this->getAccountBalance();

        if (!$this->prevBalance)
        {
            $this->prevBalance = $balance;
        }

        return $this->currentBalance = $balance;
    }

    abstract public function getAccountBalance(): AccountBalance;

    abstract public function orderBook(string $symbol): OrderBook;

    /**
     * @return string[]
     */
    abstract public function symbols(string $quoteAsset = null): array;

    abstract public function symbol(string $baseAsset, string $quoteAsset): ?string;

    abstract public function minTradeQuantity(string $symbol): float;

    abstract public function candleMap(): CandleMap;

    abstract public function candles(string $symbol, string $interval, int $start = null, int $limit = null): array;

    /**
     * @param Order[] $responses
     */
    abstract protected function processOrderResponses(array $responses): Collection;

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

    abstract protected function updateOrderDetails(Order $order, array $response): void;
}