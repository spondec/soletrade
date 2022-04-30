<?php

namespace Tests\Unit\Trade;

use App\Trade\Side;
use PHPUnit\Framework\TestCase;

class SideTest extends TestCase
{
    public function test_is_buy()
    {
        $this->assertEquals(true, Side::from('BUY')->isBuy());
        $this->assertEquals(false, Side::from('SELL')->isBuy());
    }

    public function test_opposite()
    {
        $this->assertEquals(Side::SELL, Side::from('BUY')->opposite());
        $this->assertEquals(Side::BUY, Side::from('SELL')->opposite());
    }
}
