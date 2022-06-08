<?php

namespace Tests\Feature\Trade\Exchange;

use App\Trade\CandleMap;
use App\Trade\Exchange\Account\Asset;
use App\Trade\Exchange\Account\Balance;
use App\Trade\Exchange\Exchange;
use App\Trade\Exchange\Fetcher;
use App\Trade\Exchange\OrderBook;
use Tests\TestCase;

class FetcherTest extends TestCase
{
    public function test_balance_update(): void
    {
        $exchangeMock = \Mockery::mock(Exchange::class);
        $fetcherMock = \Mockery::mock(Fetcher::class, [$exchangeMock]);

        $updatedBalance = new Balance($exchangeMock, [
            new Asset('BTC', 9, 5),
            new Asset('ETH', 8, 4),
        ]);

        $exchangeMock
            ->shouldReceive('fetch')
            ->andReturn($fetcherMock);

        $fetcherMock
            ->shouldReceive('balance')
            ->once()
            ->andReturn($updatedBalance);

        $fetcher = $this->getFetcher($exchangeMock);
        $balance = $fetcher->balance();

        $btc = $balance['BTC'];
        $eth = $balance['ETH'];

        $this->assertEquals(10, $btc->total());
        $this->assertEquals(10, $btc->available());

        $this->assertEquals(10, $eth->total());
        $this->assertEquals(10, $eth->available());

        $balance->update();

        $this->assertEquals(9, $btc->total());
        $this->assertEquals(5, $btc->available());

        $this->assertEquals(8, $eth->total());
        $this->assertEquals(4, $eth->available());
    }

    protected function getFetcher(Exchange $exchange): Fetcher
    {
        return new class($exchange) extends Fetcher
        {
            protected function fetchOrderBook(string $symbol): OrderBook
            {
                // TODO: Implement fetchOrderBook() method.
            }

            protected function fetchSymbols(string $quoteAsset = null): array
            {
                // TODO: Implement fetchSymbols() method.
            }

            protected function fetchMinimumQuantity(string $symbol): float
            {
                // TODO: Implement fetchMinTradeQuantity() method.
            }

            protected function fetchCandles(string $symbol, string $interval, int $start = null, int $limit = null): array
            {
                // TODO: Implement fetchCandles() method.
            }

            public function getMaxCandlesPerRequest(): int
            {
                // TODO: Implement getMaxCandlesPerRequest() method.
            }

            public function candleMap(): CandleMap
            {
                // TODO: Implement candleMap() method.
            }

            protected function fetchAccountBalance(): Balance
            {
                return $this->newBalance([
                    $this->newAsset('BTC', 10, 10),
                    $this->newAsset('ETH', 10, 10),
                ]);
            }
        };
    }
}
