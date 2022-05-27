<?php

namespace Tests\Unit\Trade;

use App\Trade\Exchange\Account\AllocatedAsset;
use App\Trade\Exchange\Account\TradeAsset;
use Mockery as m;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TradeAssetTest extends m\Adapter\Phpunit\MockeryTestCase
{
    public function test_register_roi()
    {
        $allocation = m::mock('alias:' . AllocatedAsset::class);

        $allocation
            ->shouldReceive('amount')
            ->andReturns(100);

        $allocation->leverage = 5;

        $allocation->shouldReceive('allocate')
            ->once()
            ->with(30);

        $tradeAsset = new TradeAsset($allocation);

        $tradeAsset->registerRoi(50);

        $allocation->shouldReceive('allocate')
            ->once()
            ->with(10);

        $tradeAsset->registerRoi(-50);
    }
}
