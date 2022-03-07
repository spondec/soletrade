<?php

namespace App\Trade\Exchange\Binance\Spot;

use App\Trade\CandleMap;
use App\Trade\Exchange\Account\Balance;
use App\Trade\Exchange\Exchange;
use App\Trade\Exchange\OrderBook;
use ccxt\binance;
use JetBrains\PhpStorm\Pure;

class Fetcher extends \App\Trade\Exchange\Fetcher
{
    protected array $limits = [];

    public function __construct(protected Exchange $exchange,
                                protected binance  $api)
    {
        parent::__construct($exchange);
    }

    #[Pure] public function candleMap(): CandleMap
    {
        return new CandleMap(0, 1, 4, 2, 3, 5);
    }

    public function getMaxCandlesPerRequest(): int
    {
        return 1000;
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

    protected function buildSymbol(string $baseAsset, string $quoteAsset): string
    {
        return \mb_strtoupper("$baseAsset/$quoteAsset");
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

        foreach ($markets as $market)
        {
            $this->limits[$market['symbol']] = $market['limits'];
        }

        if ($quoteAsset)
        {
            $markets = \array_filter($markets, static fn($v) => $v['info']['quoteAsset'] === $quoteAsset);
        }

        return \array_column($markets, 'symbol');
    }

    protected function fetchCandles(string $symbol, string $interval, int $start = null, int $limit = null): array
    {
        return $this->api->fetch_ohlcv($symbol, $interval, $start, $limit);
    }

    protected function fetchMinTradeQuantity(string $symbol): float
    {
        if (empty($this->limits[$symbol]))
        {
            $this->symbols();
        }

        $minQuantity = $this->limits[$symbol]['amount']['min'];
        $minCost = $this->limits[$symbol]['cost']['min'];
        $price = $this->orderBook($symbol)->bestBid();

        return ($quantity = $minCost / $price) < $minQuantity ? $minQuantity : $quantity;
    }
}