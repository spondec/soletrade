<?php

namespace Tests\Unit\Trade;

use App\Trade\CandleCollection;
use PHPUnit\Framework\TestCase;

class CandleCollectionTest extends TestCase
{
    public function test_previous_candles()
    {
        $collection = new CandleCollection(range(1, 10));

        $res = $collection->previousCandles($count = 2, 5);

        $this->assertEquals(4, $res[0]);
        $this->assertEquals(5, $res[1]);
        $this->assertCount($count, $res);
    }

    public function test_previous_candles_throws_exception()
    {
        $collection = new CandleCollection(range(1, 10));

        $this->expectExceptionMessage('Not enough candles exist');
        $collection->previousCandles(3, 2);
    }
}
