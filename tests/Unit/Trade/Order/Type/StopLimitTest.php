<?php

namespace Trade\Order\Type;

use App\Models\Order;
use App\Trade\Order\Type\StopLimit;
use App\Trade\Side;
use Mockery\MockInterface;
use Tests\Unit\Trade\Order\Type\HandlerTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class StopLimitTest extends HandlerTestCase
{
    public function test_order_reduce_only_false()
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(StopLimit::class, $pos, $manager);
        $handler->spreadRatio = 0.001;

        $manager->shouldReceive('stopLimit')
            ->once()
            ->withArgs([Side::SELL, 100 + 100 * $handler->spreadRatio, 100, 1, false])
            ->andReturns(new Order());

        $this->assertOrder($handler, 1, 100, false);
    }

    public function test_order_reduce_only_true()
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(StopLimit::class, $pos, $manager);
        $handler->spreadRatio = 0.001;

        $manager->shouldReceive('stopLimit')
            ->once()
            ->withArgs([Side::BUY, 100 - 100 * $handler->spreadRatio, 100, 1, true])
            ->andReturns(new Order());

        $this->assertOrder($handler, 1, 100, true);
    }
}
