<?php

namespace Tests\Unit\Trade;

use App\Trade\Calc;
use PHPUnit\Framework\TestCase;

class CalcTest extends TestCase
{
    public function test_duration(): void
    {
        $this->assertEquals(60, Calc::duration('1m'));
        $this->assertEquals(60 * 5, Calc::duration('5m'));
        $this->assertEquals(60 ** 2, Calc::duration('1h'));
        $this->assertEquals(60 ** 2 * 4, Calc::duration('4h'));
        $this->assertEquals(60 ** 2 * 24, Calc::duration('1d'));
        $this->assertEquals(60 ** 2 * 24 * 7, Calc::duration('1w'));
        $this->assertEquals(60 ** 2 * 24 * 30, Calc::duration('1M'));
    }

    public function test_realize_price()
    {
        $this->assertEquals(false, Calc::realizePrice(true, 10.64, 10.66, 10.65));
        $this->assertEquals(10.63, Calc::realizePrice(true, 10.64, 10.63, 10.61));
        $this->assertEquals(10.64, Calc::realizePrice(true, 10.64, 10.91, 10.61));

        $this->assertEquals(10.65, Calc::realizePrice(false, 10.64, 10.66, 10.65));
        $this->assertEquals(false, Calc::realizePrice(false, 10.64, 10.63, 10.61));
        $this->assertEquals(10.64, Calc::realizePrice(false, 10.64, 10.91, 10.61));
    }

    public function test_pnl()
    {
        $this->assertEquals(5, Calc::pnl(100, 5));
        $this->assertEquals(-5, Calc::pnl(100, -5));
        $this->assertEquals(100, Calc::pnl(100, 100));
        $this->assertEquals(-100, Calc::pnl(100, -100));
        $this->assertEquals(-200, Calc::pnl(100, -200));
        $this->assertEquals(0, Calc::pnl(100, 0));
    }

    public function test_roi()
    {
        $this->assertEquals(50, Calc::roi(true, 100,150));
        $this->assertEquals(-1, Calc::roi(true, 100,99));
        $this->assertEquals(50, Calc::roi(false, 100,50));
        $this->assertEquals(-1, Calc::roi(false, 100,101));
    }

    public function test_avg()
    {
        $this->assertEquals(10, Calc::avg([10, 10, 10]));
        $this->assertEquals(10, Calc::avg([10, 9, 11]));
    }
}
