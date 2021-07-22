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

        $this->assertOrder($order, 'BUY', 'LIMIT', 'OPEN', 'new');
    }

    public function test_place_limit_sell_order()
    {
        $order = $this->exchange->limit('SELL',
            $symbol = $this->getSymbol(),
            $this->getLimitSellPrice($symbol),
            $this->getQuantity($symbol)
        );

        $this->assertOrder($order, 'SELL', 'LIMIT', 'OPEN', 'new');
    }

    public function test_place_stop_limit_order()
    {
        $order = $this->exchange->stopLimit('SELL',
            $symbol = $this->getSymbol(),
            $stopPrice = $this->getLimitBuyPrice($symbol),
            $stopPrice + $stopPrice * 0.001,
            $this->getQuantity($symbol)
        );

        $this->assertOrder($order, 'SELL', 'STOP_LOSS_LIMIT', 'OPEN', 'new');
    }

    public function test_place_market_buy_order()
    {
        $order = $this->exchange->market('BUY',
            $symbol = $this->getSymbol(),
            $this->getQuantity($symbol));

        $this->assertOrder($order, 'BUY', 'MARKET', 'CLOSED', 'new');
    }

    public function test_place_market_sell_order()
    {
        $order = $this->exchange->market('SELL',
            $symbol = $this->getSymbol(),
            $this->getQuantity($symbol));

        $this->assertOrder($order, 'SELL', 'MARKET', 'CLOSED', 'new');
    }

//    public function test_place_stop_market_sell_order()
//    {
//        $order = $this->exchange->stopMarket('SELL',
//            $symbol = $this->getSymbol(),
//            $this->getQuantity($symbol),
//            $this->getLimitBuyPrice($symbol));
//
//        $this->assertOrder($order, 'SELL', 'STOP_LOSS', 'OPEN', 'new');
//    }

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

    protected function assertOrderTypeContains(Order $order, string $expected)
    {
        if (!str_contains($order->type, mb_strtoupper($expected)))
        {
            throw new \UnexpectedValueException("Order type assertion failed. 
                Expected: $expected \n Actual: $order->type");
        }
    }

    protected function assertOrder(Order $order, string $side, string $type, string $status, string $responseKey)
    {
        $order->validate();

        $this->assertEquals($side, $order->side);
        $this->assertEquals($status, $order->status);
        $this->assertOrderTypeContains($order, $type);
        $this->assertOrderResponseExists($order, $responseKey);
    }

    protected function getSymbol(): string
    {
        $primaryAsset = $this->exchange->accountBalance()->primaryAsset()->name();

        return $this->exchange->symbols($primaryAsset)[0] ?? static::$defaultSymbol;
    }

    protected function getQuantity(string $symbol): float
    {
        return $this->exchange->minTradeQuantity($symbol);
    }

    protected function assertOrderResponseExists(Order $order, string $responseKey): void
    {
        $this->assertTrue(array_key_exists($responseKey, $order->responses));
    }
}
