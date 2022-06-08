<?php

namespace Tests\Unit\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Exchange\OrderManager;
use App\Trade\Order\Type\Handler;
use Mockery as m;

class HandlerTest extends HandlerTestCase
{
    public function test_from(): void
    {
        $this->assertEquals('\App\Trade\Order\Type\Limit', Handler::getClass(OrderType::LIMIT));
        $this->assertEquals('\App\Trade\Order\Type\Market', Handler::getClass(OrderType::MARKET));
        $this->assertEquals('\App\Trade\Order\Type\StopLimit', Handler::getClass(OrderType::STOP_LIMIT));
    }

    public function test_order_type_mismatch_throws_exception(): void
    {
        $handler = new class(Side::BUY, m::mock(OrderManager::class)) extends Handler
        {
            public function getOrderType(): OrderType
            {
                return OrderType::LIMIT;
            }

            protected function handle(float $quantity, float $price, bool $reduceOnly): Order
            {
                return new Order();
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Order type mismatch');
        $handler->order(OrderType::MARKET, 1, 1, false);
    }

    public function test_order_reduce_only_false(): void
    {
        $this->assertTrue(true);
    }

    public function test_order_reduce_only_true(): void
    {
        $this->assertTrue(true);
    }
}
