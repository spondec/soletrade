<?php

namespace Tests\Unit\Trade\Collection;

use App\Models\TradeSetup;
use App\Trade\Collection\TradeCollection;
use PHPUnit\Framework\TestCase;
use Mockery as m;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TradeCollectionTest extends TestCase
{
    public function test_get_first_trade()
    {
        $trade = $this->newTrade();
        $tradeCollection = new TradeCollection([$trade]);

        $this->assertEquals($trade, $tradeCollection->getFirstTrade());
    }

    protected function newTrade(): m\MockInterface&TradeSetup
    {
        return m::mock('alias:' . TradeSetup::class);
    }

    public function test_find_next_trade()
    {
        $trade = $this->newTrade();
        $trade->shouldReceive('isBuy')->andReturn(false);
        $trade->timestamp = time();

        $trade2 = $this->newTrade();
        $trade2->shouldReceive('isBuy')->andReturn(true);
        $trade2->timestamp = $trade->timestamp + 1;

        $trade3 = $this->newTrade();
        $trade3->shouldReceive('isBuy')->andReturn(true);
        $trade3->timestamp = $trade->timestamp + 2;

        $tradeCollection = new TradeCollection([$trade, $trade2, $trade3], ['oppositeOnly' => false]);

        $this->assertEquals($trade2, $tradeCollection->getNextTrade($trade));
        $this->assertEquals($trade3, $tradeCollection->getNextTrade($trade2));

        $tradeCollection = new TradeCollection([$trade], ['oppositeOnly' => false]);
        $this->assertNull($tradeCollection->getNextTrade($trade));
    }

    public function test_get_next_opposite_trade()
    {
        $trade = $this->newTrade();
        $trade->shouldReceive('isBuy')->andReturn(true);
        $trade->timestamp = time();

        $trade2 = $this->newTrade();
        $trade2->shouldReceive('isBuy')->andReturn(true);
        $trade2->timestamp = $trade->timestamp + 1;

        $trade3 = $this->newTrade();
        $trade3->shouldReceive('isBuy')->andReturn(false);
        $trade3->timestamp = $trade->timestamp + 2;

        $tradeCollection = new TradeCollection([$trade, $trade2, $trade3], ['oppositeOnly' => true]);

        $this->assertEquals($trade3, $tradeCollection->getNextTrade($trade));

        $tradeCollection = new TradeCollection([$trade], ['oppositeOnly' => true]);
        $this->assertNull($tradeCollection->getNextTrade($trade));
    }
}
