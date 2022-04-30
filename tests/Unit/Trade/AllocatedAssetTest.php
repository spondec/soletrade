<?php

namespace Tests\Unit\Trade;

use App\Trade\AllocatedAsset;
use App\Trade\Exchange\Account\Asset;
use App\Trade\Exchange\Account\Balance;
use PHPUnit\Framework\TestCase;

class AllocatedAssetTest extends TestCase
{
    public function test_get_real_size()
    {
        $asset = \Mockery::mock(Asset::class);
        $asset
            ->shouldReceive('available')
            ->andReturn(1000);

        $tradeAsset = $this->getTradeAsset($asset, 1000);

        $this->assertEquals(100, $tradeAsset->getRealSize(10));
        $this->assertEquals(1000, $tradeAsset->getRealSize(100));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Proportional size exceeds the maximum proportional position size');
        $tradeAsset->getRealSize(101);
    }

    public function test_get_real_size_takes_0_throws_exception()
    {
        $asset = \Mockery::mock(Asset::class);
        $asset
            ->shouldReceive('available')
            ->andReturn(1000);

        $tradeAsset = $this->getTradeAsset($asset, 1000);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Argument $value must be greater than zero');
        $tradeAsset->getRealSize(0);
    }

    public function test_get_proportional_size_takes_0_throws_exception()
    {
        $asset = \Mockery::mock(Asset::class);
        $asset
            ->shouldReceive('available')
            ->andReturn(1000);

        $tradeAsset = $this->getTradeAsset($asset, 1000);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Argument $value must be greater than zero');
        $tradeAsset->getProportionalSize(0);
    }

    protected function getTradeAsset(Asset $asset, $allocation): AllocatedAsset
    {
        $balance = \Mockery::mock(Balance::class);
        $balance
            ->shouldReceive('update')
            ->once()
            ->andReturn($balance);

        return new AllocatedAsset($balance, $asset, $allocation);
    }

    public function test_get_proportional_size()
    {
        $asset = \Mockery::mock(Asset::class);
        $asset
            ->shouldReceive('available')
            ->andReturn(1000);

        $tradeAsset = $this->getTradeAsset($asset, 1000);

        $this->assertEquals(10, $tradeAsset->getProportionalSize(100));
        $this->assertEquals(100, $tradeAsset->getProportionalSize(1000));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Argument $size exceeds the allocated asset amount');
        $tradeAsset->getProportionalSize(1001);
    }
}
