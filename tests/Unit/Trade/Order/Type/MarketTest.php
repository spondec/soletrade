<?php

namespace Tests\Unit\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Order\Type\Market;
use App\Trade\Side;
use Mockery\MockInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MarketTest extends HandlerTestCase
{
    public function test_order_reduce_only_false()
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(Market::class, $pos, $manager);

        $manager
            ->shouldReceive('market')
            ->once()
            ->withArgs([Side::SELL, $quantity = 1, false])
            ->andReturns(new Order());

        $this->assertOrder($handler, $quantity, 100, false);
    }

    public function test_order_reduce_only_true()
    {
        /** @var MockInterface $manager */
        $handler = $this->getHandler(Market::class, $pos, $manager);

        $manager
            ->shouldReceive('market')
            ->once()
            ->withArgs([Side::BUY, $quantity = 1, true])
            ->andReturns(new Order());

        $this->assertOrder($handler, $quantity, 100, true);
    }
}
