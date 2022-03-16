<?php

namespace Trade;

use App\Trade\Exchange\Account\Asset;
use App\Trade\Exchange\Account\Balance;
use App\Trade\TradeAsset;
use PHPUnit\Framework\TestCase;

class TradeAssetTest extends TestCase
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
        $this->expectExceptionMessage('Argument $proportionalSize exceeds the maximum position size');
        $tradeAsset->getRealSize(101);

        $this->expectExceptionMessage('Argument $value must be greater than 0');
        $tradeAsset->getRealSize(0);
    }

    protected function getTradeAsset(Asset $asset, $allocation): TradeAsset
    {
        $balance = \Mockery::mock(Balance::class);

        return new TradeAsset($balance, $asset, $allocation);
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

        $this->expectExceptionMessage('Argument $value must be greater than 0');
        $tradeAsset->getProportionalSize(0);
    }
}
