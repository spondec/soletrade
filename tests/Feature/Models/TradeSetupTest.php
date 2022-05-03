<?php

namespace Tests\Feature\Models;

use App\Models\TradeSetup;
use App\Trade\Enum\Side;
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

    public function test_set_stop_price()
    {
        $trade = new TradeSetup();
        $trade->price = 1000;
        $trade->side = Side::BUY->value;

        $trade->setStopPrice(0.001, 0.0005);
        $this->assertEquals(999, $trade->stop_price);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Trigger price ratio can not be less than or equal to the stop price percent');
        $trade->setStopPrice(0.001, 0.001);
    }

    public function test_set_target_price()
    {
        $trade = new TradeSetup();
        $trade->price = 1000;
        $trade->side = Side::BUY->value;

        $trade->setTargetPrice(0.05);
        $this->assertEquals(1050, $trade->target_price);
    }
}
