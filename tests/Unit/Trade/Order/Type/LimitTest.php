<?php

namespace Trade\Order\Type;

use App\Models\Order;
use App\Trade\Order\Type\Limit;
use App\Trade\Side;
use Mockery\MockInterface;
use Tests\Unit\Trade\Order\Type\HandlerTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LimitTest extends HandlerTestCase
{
    public function test_order_reduce_only_false()
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(Limit::class, $pos, $manager);

        $manager
            ->shouldReceive('limit')
            ->once()
            ->withArgs([Side::SELL, $price = 100, $quantity = 1, false])
            ->andReturns(new Order());

        $this->assertOrder($handler, $quantity, $price, false);
    }

    public function test_order_reduce_only_true()
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(Limit::class, $pos, $manager);

        $manager
            ->shouldReceive('limit')
            ->once()
            ->withArgs([Side::BUY, $price = 100, $quantity = 1, true])
            ->andReturns(new Order());

        $this->assertOrder($handler, $quantity, $price, true);
    }
}
