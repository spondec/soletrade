<?php

namespace App\Trade\Exchange;

use App\Trade\CandleMap;
use App\Trade\Exchange\Account\Asset;
use App\Trade\Exchange\Account\Balance;

abstract class Fetcher
{
    protected ?Balance $prevBalance = null;
    protected Balance $currentBalance;

    public function __construct(protected Exchange $exchange)
    {
    }

    protected function newAsset(string $name, float $total, float $available): Asset
    {
        return new Asset($name, $total, $available);
    }

    /**
     * @param Asset[] $assets
     *
     * @return Balance
     */
    protected function newBalance(array $assets): Balance
    {
        return new Balance($this->exchange, $assets);
    }

    /**
     * @param string $symbol
     *
     * @return OrderBook
     * @throws \App\Exceptions\EmptyOrderBookException
     */
    public function orderBook(string $symbol): OrderBook
    {
        return $this->fetchOrderBook($symbol);
    }

    abstract protected function fetchOrderBook(string $symbol): OrderBook;

    public function symbols(string $quoteAsset = null): array
    {
        return $this->fetchSymbols($quoteAsset);
    }

    /**
     * @return string[]
     */
    abstract protected function fetchSymbols(string $quoteAsset = null): array;

    public function symbol(string $baseAsset, string $quoteAsset): ?string
    {
        return $this->buildSymbol($baseAsset, $quoteAsset);
    }

    abstract protected function buildSymbol(string $baseAsset, string $quoteAsset): ?string;

    public function minTradeQuantity(string $symbol): float
    {
        return $this->fetchMinTradeQuantity($symbol);
    }

    abstract protected function fetchMinTradeQuantity(string $symbol): float;

    public function candles(string $symbol, string $interval, int $start = null, int $limit = null): array
    {
        return $this->fetchCandles($symbol, $interval, $start, $limit);
    }

    abstract protected function fetchCandles(string $symbol, string $interval, int $start = null, int $limit = null): array;

    abstract public function getMaxCandlesPerRequest(): int;

    abstract public function candleMap(): CandleMap;

    /**
     * Get ROI relative to the initial balance since the exchange was instantiated.
     *
     * @return array|null
     */
    public function roi(): ?array
    {
        if (!$this->prevBalance)
        {
            return null;
        }

        return $this->balance()->calculateRoi($this->prevBalance);
    }

    /** @noinspection PhpUndefinedFieldInspection */
    protected function registerBalanceListeners(Balance $balance): void
    {
        foreach ($balance->assets as $asset)
        {
            $balance->listen('update',
                \Closure::bind(function (Balance $current, Balance $updated) {
                    $asset = $updated->assets[$this->name];

                    $this->total = $asset->total();
                    $this->available = $asset->available();
                }, $asset, $asset));
        }
    }

    /**
     * Fetch the latest account balance from the exchange.
     *
     * @return Balance
     */
    public function balance(): Balance
    {
        $balance = $this->fetchAccountBalance();

        $this->registerBalanceListeners($balance);

        if (!$this->prevBalance)
        {
            $this->prevBalance = $balance;
        }

        return $this->currentBalance = $balance;
    }

    abstract protected function fetchAccountBalance(): Balance;
}