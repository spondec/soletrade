<?php

declare(strict_types=1);

namespace App\Trade\Exchange\CCXT;

use App\Trade\CandleMap;
use App\Trade\Exchange\Account\Balance;
use App\Trade\Exchange\Exchange;
use App\Trade\Exchange\OrderBook;

abstract class Fetcher extends \App\Trade\Exchange\Fetcher
{
    protected array $limits = [];

    public function __construct(Exchange $exchange, protected \ccxt\Exchange $api)
    {
        parent::__construct($exchange);
    }

    public function candleMap(): CandleMap
    {
        return new CandleMap(0, 1, 4, 2, 3, 5);
    }

    protected function fetchOrderBook(string $symbol): OrderBook
    {
        $orderBook = $this->api->fetch_order_book($symbol);

        return new OrderBook($symbol,
            \array_column($orderBook['bids'], 0),
            \array_column($orderBook['asks'], 0));
    }

    protected function fetchSymbols(string $quoteAsset = null): array
    {
        $markets = $this->api->fetch_markets();

        if ($quoteAsset)
        {
            $markets = \array_filter($markets, static fn($v) => $v['quote'] === $quoteAsset);
        }

        return \array_column($markets, $this->getSymbolColumnKey());
    }

    abstract protected function getSymbolColumnKey(): string;

    protected function fetchMinimumQuantity(string $symbol): float
    {
        if (empty($this->limits[$symbol]))
        {
            $this->cacheLimits();
        }

        $minQuantity = $this->limits[$symbol]['amount']['min'];
        $minCost = $this->limits[$symbol]['cost']['min'];
        $price = $this->orderBook($symbol)->bestBid();

        return ($quantity = $minCost / $price) < $minQuantity ? $minQuantity : $quantity;
    }

    /**
     * @return array
     * @throws \ccxt\BadResponse
     */
    protected function cacheLimits(): array
    {
        $markets = $this->api->fetch_markets();

        foreach ($markets as $market)
        {
            $this->limits[$market['id']] = $market['limits'];
        }
        return $markets;
    }

    protected function fetchCandles(string $symbol, string $interval, int $start = null, int $limit = null): array
    {
        return $this->api->fetch_ohlcv($symbol, $interval, $start, $limit);
    }

    protected function fetchAccountBalance(): Balance
    {
        $result = $this->api->fetch_balance();
        $assets = [];

        foreach ($result['total'] as $asset => $total)
        {
            $assets[] = $this->newAsset($asset, $total, $result['free'][$asset]);
        }

        return $this->newBalance($assets);
    }
}