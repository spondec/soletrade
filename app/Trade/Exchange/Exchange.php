<?php

namespace App\Trade\Exchange;

use App\Models\Exchange as ExchangeModel;
use App\Repositories\ConfigRepository;
use App\Trade\HasName;
use App\Trade\CandleUpdater;
use Illuminate\Support\Facades\App;

abstract class Exchange
{
    use HasName;

    /**
     * @var array<class-string<Exchange>,Exchange>
     */
    protected static array $instances = [];
    protected ?string $apiKey;
    protected ?string $secretKey;

    protected ExchangeModel $exchange;
    protected Fetcher $fetch;
    protected Orderer $order;
    private CandleUpdater $update;

    protected array $config;

    private function __construct()
    {
        /** @var ConfigRepository $repo */
        $repo = App::make(ConfigRepository::class);

        if (!$this->config = $repo->exchangeConfig(static::name()))
        {
            throw new \InvalidArgumentException('Invalid config for ' . static::name());
        }

        $this->apiKey = $this->config['apiKey'] ?? null;
        $this->secretKey = $this->config['secretKey'] ?? null;

        $this->setup();
        $this->register();

        $this->update = App::make(CandleUpdater::class, ['exchange' => $this]);
    }

    abstract protected function setup(): void;

    private function register(): void
    {
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->exchange = ExchangeModel::query()
            ->firstOrCreate(['class' => static::class], [
                'class' => static::class,
                'name'  => static::name(),
            ]);
    }

    public static function instance(): static
    {
        if (!$instance = static::$instances[static::class] ?? null)
        {
            return static::$instances[static::class] = new static();
        }

        return $instance;
    }

    public function order(): Orderer
    {
        return $this->order;
    }

    public function fetch(): Fetcher
    {
        return $this->fetch;
    }

    /**
     * @param class-string<Exchange>\string $nameOrClass
     *
     * @return Exchange
     */
    public static function from(string $nameOrClass): Exchange
    {
        if (class_exists($nameOrClass))
        {
            return $nameOrClass::instance();
        }

        return \Config::get("trade.exchanges.$nameOrClass.class")::instance();
    }

    public function update(): CandleUpdater
    {
        return $this->update;
    }

    public function info(): array
    {
        return [
            'name' => static::name()
        ];
    }

    public function model(): ExchangeModel
    {
        return $this->exchange;
    }
}