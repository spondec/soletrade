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
     * @throws \App\Trade\Exception\EmptyOrderBookException
     *
     * @return OrderBook
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
     * Fetches the list of available symbols from the exchange.
     *
     * Example:
     *
     * [
     *    0 => 'BTC/USDT',
     *    1 => 'ETH/USDT',
     * ]
     *
     * @return string[]
     */
    abstract protected function fetchSymbols(string $quoteAsset = null): array;

    public function minimumQuantity(string $symbol): float
    {
        return $this->fetchMinimumQuantity($symbol);
    }

    abstract protected function fetchMinimumQuantity(string $symbol): float;

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

    protected function registerBalanceListeners(Balance $balance): void
    {
        foreach ($balance->assets as $asset)
        {
            $balance->listen(
                'update',
                \Closure::bind(function (Balance $current, Balance $updated) use ($asset)
                {
                    $updatedAsset = $updated[$this->name];

                    $asset->total = $updatedAsset->total();
                    $asset->available = $updatedAsset->available();
                }, $asset, $asset)
            );
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
