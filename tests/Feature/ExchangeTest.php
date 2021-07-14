<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Trade\Exchange\AbstractExchange;
use App\Trade\Exchange\AccountBalance;
use Tests\TestCase;

abstract class ExchangeTest extends TestCase
{
    protected AbstractExchange $exchange;

    protected static string $defaultSymbol;

    abstract protected function setupExchange(): AbstractExchange;

    abstract protected function getLimitBuyPrice(string $symbol): float;

    abstract protected function getLimitSellPrice(string $symbol): float;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exchange = $this->setupExchange();
    }

    public function test_candle_map_keys()
    {
        $map = $this->exchange->candleMap();
        $keys = ['close', 'high', 'low', 'open', 'volume', 'timestamp'];

        foreach ($keys as $key)
        {
            $this->assertArrayHasKey($key, $map);
        }
    }

    public function test_fetch_candles()
    {
        $interval = ['text' => '15m', 'seconds' => 900];

        $time = time();
        $candles = $this->exchange->candles($this->getSymbol(), $interval['text']);
        $map = $this->exchange->candleMap();

        foreach ($map as $key)
        {
            $this->assertArrayHasKey($key, $candles[0]);
        }

        $this->assertEquals(($time - $time % $interval['seconds']) * 1000,
            end($candles)[$map['timestamp']],
            "The last timestamp doesn't match with the current timestamp of the interval.");
    }

    public function test_get_account_balance()
    {
        $this->assertInstanceOf(AccountBalance::class, $this->exchange->accountBalance());
    }

    public function test_place_limit_buy_order()
    {
        $order = $this->exchange->limit('BUY',
            $symbol = $this->getSymbol(),
            $this->getLimitBuyPrice($symbol),
            $this->getQuantity($symbol)
        );

        $this->assertOrder($order, 'BUY', 'OPEN', 'new');
    }

    public function test_place_limit_sell_order()
    {
        $order = $this->exchange->limit('SELL',
            $symbol = $this->getSymbol(),
            $this->getLimitSellPrice($symbol),
            $this->getQuantity($symbol)
        );

        $this->assertOrder($order, 'SELL', 'OPEN', 'new');
    }

    public function test_get_open_orders()
    {
        $orders = $this->exchange->openOrders($this->getSymbol());

        $this->assertNotEmpty($orders, 'There are no open orders.');
    }

    public function test_sync_orders()
    {
        $orders = $this->exchange->openOrders($this->getSymbol());

        foreach ($orders as $order)
        {
            $this->exchange->syncOrder($order);
            $this->assertOrderResponseExists($order, 'update');
        }
    }

    public function test_cancel_open_orders()
    {
        $orders = $this->exchange->openOrders($this->getSymbol());

        foreach ($orders as $order)
        {
            $this->exchange->cancelOrder($order);
            $this->assertEquals('CANCELED', $order->status);
        }
    }

    protected function assertOrder(Order $order, string $side, string $status, string $responseKey = null)
    {
        $order->validate();
        $this->assertEquals($side, $order->side);
        $this->assertEquals($status, $order->status);
        if ($responseKey) $this->assertOrderResponseExists($order, 'new');
    }

    protected function getSymbol(): string
    {
        $primaryAsset = $this->exchange->accountBalance()->primaryAsset()->name();

        return $this->exchange->symbolList($primaryAsset)[0] ?? static::$defaultSymbol;
    }

    protected function getQuantity(string $symbol)
    {
        return $this->exchange->minTradeQuantity($symbol);
    }

    protected function assertOrderResponseExists(Order $order, string $responseKey): void
    {
        $this->assertTrue(array_key_exists($responseKey, $order->responses));
    }
}
