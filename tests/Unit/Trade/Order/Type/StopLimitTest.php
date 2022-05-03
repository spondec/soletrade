<?php

namespace Tests\Unit\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Enum\Side;
use App\Trade\Order\Type\StopLimit;
use Mockery\MockInterface;
use Tests\Unit\Trade\Order\Type\HandlerTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class StopLimitTest extends HandlerTestCase
{
    public function test_order_reduce_only_false(): void
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(StopLimit::class, $pos, $manager, ['trigger_price_ratio' => 0.001]);

        $manager->shouldReceive('stopLimit')
            ->once()
            ->withArgs([Side::SELL, 100 + 100 * 0.001, 100, 1, false])
            ->andReturns(new Order());

        $this->assertOrder($handler, 1, 100, false);
    }

    public function test_order_reduce_only_true(): void
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(StopLimit::class, $side, $manager, ['trigger_price_ratio' => 0.001]);

        $manager->shouldReceive('stopLimit')
            ->once()
            ->withArgs([Side::BUY, 100 - 100 * 0.001, 100, 1, true])
            ->andReturns(new Order());

        $this->assertOrder($handler, 1, 100, true);
    }
}
