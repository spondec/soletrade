<?php

namespace Trade;

use App\Trade\Side;
use PHPUnit\Framework\TestCase;

class SideTest extends TestCase
{
    public function test_get_exit_side()
    {
        $this->assertEquals(Side::SELL, Side::getExitSide(Side::BUY));
        $this->assertEquals(Side::BUY, Side::getExitSide(Side::SELL));
    }

    public function test_is_buy()
    {
        $this->assertEquals(true, Side::from('BUY')->isBuy());
        $this->assertEquals(false, Side::from('SELL')->isBuy());
    }
}
