<?php

namespace Trade;

use App\Trade\Calc;
use PHPUnit\Framework\TestCase;

class CalcTest extends TestCase
{
    public function testRoi()
    {

    }

    public function testRiskReward()
    {

    }

    public function testPnl()
    {

    }

    public function testInRange()
    {

    }

    public function testDuration()
    {
        $this->assertEquals(60, Calc::duration('1m'));
        $this->assertEquals(60 * 5, Calc::duration('5m'));
        $this->assertEquals(60 ** 2, Calc::duration('1h'));
        $this->assertEquals(60 ** 2 * 4, Calc::duration('4h'));
        $this->assertEquals(60 ** 2 * 24, Calc::duration('1d'));
        $this->assertEquals(60 ** 2 * 24 * 7, Calc::duration('1w'));
        $this->assertEquals(60 ** 2 * 24 * 30, Calc::duration('1M'));
    }
}
