<?php

namespace Models;

use App\Models\TradeSetup;
use App\Trade\Side;
use PHPUnit\Framework\TestCase;

class TradeSetupTest extends TestCase
{
    public function test_set_side()
    {
        $tradeSetup = new TradeSetup();

        $tradeSetup->setSide(Side::BUY);
        $this->assertEquals(Side::BUY, $tradeSetup->side());

        $tradeSetup->setSide(Side::SELL);
        $this->assertEquals(Side::SELL, $tradeSetup->side());
    }

    public function test_get_side()
    {
        $tradeSetup = new TradeSetup();

        $tradeSetup->side = 'BUY';
        $this->assertInstanceOf(Side::BUY::class, $tradeSetup->side());

        $tradeSetup->side = 'SELL';
        $this->assertInstanceOf(Side::SELL::class, $tradeSetup->side());
    }

    public function test_is_buy()
    {
        $tradeSetup = new TradeSetup();

        $tradeSetup->side = 'BUY';
        $this->assertEquals(true, $tradeSetup->isBuy());

        $tradeSetup->side = 'SELL';
        $this->assertEquals(false, $tradeSetup->isBuy());
    }
}
