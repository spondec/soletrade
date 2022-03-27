<?php

namespace Tests\Unit\Trade\Order\Type;

use App\Models\Order;
use App\Models\OrderType;
use App\Trade\Evaluation\LivePosition;
use App\Trade\Order\Type\Handler;
use App\Trade\OrderManager;
use App\Trade\Side;
use Mockery as m;
use PHPUnit\Framework\TestCase;

abstract class HandlerTestCase extends TestCase
{
    abstract public function test_order_reduce_only_false();

    abstract public function test_order_reduce_only_true();

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
                                  LivePosition|m\MockInterface &$pos = null,
                                  OrderManager|m\MockInterface &$manager = null): Handler
    {
        $pos = m::mock('alias:' . LivePosition::class);
        $pos->side = Side::SELL;
        $manager = m::mock(OrderManager::class);

        return new $class(position: $pos, manager: $manager);
    }
}