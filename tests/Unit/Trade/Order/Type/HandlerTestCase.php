<?php

namespace Tests\Unit\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Enum\Side;
use App\Trade\Order\Type\Handler;
use App\Trade\OrderManager;
use Mockery as m;
use PHPUnit\Framework\TestCase;

abstract class HandlerTestCase extends TestCase
{
    abstract public function test_order_reduce_only_false(): void;

    abstract public function test_order_reduce_only_true(): void;

    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

    protected function assertOrder(Handler $handler, float $quantity, float $price, bool $reduceOnly): void
    {
        $order = $handler->order($handler->getOrderType(), $quantity, $price, $reduceOnly);
        $this->assertInstanceOf(Order::class, $order);
    }

    protected function getHandler(string                       $class,
                                  Side                         &$side = null,
                                  OrderManager|m\MockInterface &$manager = null,
                                  array                        $config = []): Handler
    {
        $manager = m::mock(OrderManager::class);

        return new $class(side: $side = Side::SELL, manager: $manager, config: $config);
    }
}