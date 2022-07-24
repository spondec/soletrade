<?php

namespace Tests\Unit\Trade;

use App\Models\Candle;
use App\Trade\Collection\CandleCollection;
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

    public function test_override_candle()
    {
        $col = new CandleCollection(range(1, 10));
        $col->overrideCandle(1, (object)[]);

        $this->assertIsObject($col[1]);
    }

    public function test_next_candle()
    {
        $col = new CandleCollection(
            $candles = Candle::factory()
                ->fillBetween(time() - 86400, time(), 600)
                ->make()
                ->sortBy('t')
                ->map(fn(Candle $v) => (object)$v->toArray())
                ->take(100)
                ->toArray()
        );

        $this->assertEquals($candles[1], $col->nextCandle($candles[0]->t));
        $this->assertEquals($candles[99], $col->nextCandle($candles[98]->t));
        $this->assertEquals(null, $col->nextCandle($candles[99]->t));
    }
}
