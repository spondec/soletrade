<?php

namespace Trade;

use App\Trade\Calc;
use PHPUnit\Framework\TestCase;

class CalcTest extends TestCase
{
    public function test_duration()
    {
        $this->assertEquals(60, Calc::duration('1m'));
        $this->assertEquals(60 * 5, Calc::duration('5m'));
        $this->assertEquals(60 ** 2, Calc::duration('1h'));
        $this->assertEquals(60 ** 2 * 4, Calc::duration('4h'));
        $this->assertEquals(60 ** 2 * 24, Calc::duration('1d'));
        $this->assertEquals(60 ** 2 * 24 * 7, Calc::duration('1w'));
        $this->assertEquals(60 ** 2 * 24 * 30, Calc::duration('1M'));
    }

    public function test_as_ms()
    {
        $time = time();
        $this->assertEquals($time * 1000, Calc::asMs($time));
        $this->assertEquals($time * 1000, Calc::asMs($time * 1000));
        $this->expectException(\LogicException::class);
        Calc::asMs($time * 10);
    }
}
