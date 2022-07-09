<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function test_as_ms(): void
    {
        $time = time();
        $this->assertEquals($time * 1000, as_ms($time));
        $this->assertEquals($time * 1000, as_ms($time * 1000));
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Argument $timestamp must be 10 or 13 digits long');
        as_ms($time * 10);
    }

    public function test_elapsed_time(): void
    {
        $time = time();
        $this->assertEquals("0:0:0:59", elapsed_time($time - 59));
        $this->assertEquals("0:0:1:0", elapsed_time($time - 60));
        $this->assertEquals("0:0:1:1", elapsed_time($time - 61));
        $this->assertEquals("0:0:1:59", elapsed_time($time - 119));
        $this->assertEquals("0:0:2:0", elapsed_time($time - 120));
        $this->assertEquals("0:0:2:1", elapsed_time($time - 121));
        $this->assertEquals("0:0:2:59", elapsed_time($time - 179));
        $this->assertEquals("0:23:59:0", elapsed_time($time - 23 * 60 * 60 - 59 * 60));
        $this->assertEquals("0:1:0:0", elapsed_time($time - 60 * 60));
        $this->assertEquals("1:0:0:0", elapsed_time($time - 86400));
        $this->assertEquals("60:0:0:0", elapsed_time($time - 86400 * 60));
        $this->assertEquals("60:1:1:1", elapsed_time($time - 86400 * 60 - 3600 - 60 - 1));
        $this->assertEquals("60:23:59:59", elapsed_time($time - 86400 * 60 - 23 * 60 ** 2 - 59 * 60 - 59));
        $this->assertEquals("61:0:0:0", elapsed_time($time - 86400 * 60 - 23 * 60 ** 2 - 59 * 60 - 60));
        $this->assertEquals("101:23:59:59", elapsed_time($time - 86400 * 101 - 23 * 60 ** 2 - 59 * 60 - 59));
        $this->assertEquals("102:0:0:0", elapsed_time($time - 86400 * 101 - 23 * 60 ** 2 - 59 * 60 - 60));
    }

    public function test_in_range()
    {
        $this->assertEquals(true, in_range(0, 0, 1));
        $this->assertEquals(true, in_range(1, 0, 1));
        $this->assertEquals(true, in_range(0.5, 0, 1));
        $this->assertEquals(true, in_range(0.0001, 0, 1));
        $this->assertEquals(false, in_range(-0.1, 0, 1));
        $this->assertEquals(false, in_range(-1, 0, 1));
        $this->assertEquals(false, in_range(2, 0, 1));
        $this->assertEquals(false, in_range(1.1, 0, 1));
    }

    public function test_binary_search()
    {
        $compare = fn($x, $y) => $x <=> $y;

        $haystack = [1, 2, 3, 4, 5]; //odd number of elements

        $this->assertEquals(0, binary_search($haystack, 1, 0, 4, $compare));
        $this->assertEquals(1, binary_search($haystack, 2, 0, 4, $compare));
        $this->assertEquals(2, binary_search($haystack, 3, 0, 4, $compare));
        $this->assertEquals(3, binary_search($haystack, 4, 0, 4, $compare));
        $this->assertEquals(4, binary_search($haystack, 5, 0, 4, $compare));
        $this->assertEquals(null, binary_search($haystack, 0, 0, 4, $compare));

        $haystack = [1, 2, 3, 4]; //even number of elements

        $this->assertEquals(0, binary_search($haystack, 1, 0, 3, $compare));
        $this->assertEquals(1, binary_search($haystack, 2, 0, 3, $compare));
        $this->assertEquals(2, binary_search($haystack, 3, 0, 3, $compare));
        $this->assertEquals(3, binary_search($haystack, 4, 0, 3, $compare));

        $haystack = [0, 5, 10, 15, 20];

        $this->assertEquals(null, binary_search($haystack, 4, 0, 5, $compare, $low, $high));
        $this->assertEquals(0, $low);
        $this->assertEquals(1, $high);
        $this->assertEquals(null, binary_search($haystack, 6, 0, 5, $compare, $low, $high));
        $this->assertEquals(1, $low);
        $this->assertEquals(2, $high);
    }
}
