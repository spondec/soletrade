<?php

namespace Tests\Feature\Trade\Indicator;

use App\Indicators\MA;
use Tests\Feature\Indicators\IndicatorTestCase;

class IndicatorTest extends IndicatorTestCase
{
    public function test_scan_without_signal_callback()
    {
        $symbol = $this->createCandles(10);
        $ma = new MA($symbol, $candles = $symbol->candles(10), ['timePeriod' => 8]);

        $data = $ma->data();
        $iterator = $data->getIterator();
        $slice = collect($candles->all())->slice(7);
        $candleIterator = $slice->getIterator();

        foreach ($ma->scan() as $result)
        {
            $this->assertEquals($iterator->key(), $result['timestamp']);
            $this->assertEquals($ma->value(), $iterator->current());
            $this->assertEquals($ma->candle(), $candleIterator->current());
            $candleIterator->next();
            $iterator->next();
        }
    }

    public function test_candle_with_timestamp()
    {
        $symbol = $this->createCandles(10);
        $ma = new MA($symbol, $candles = $symbol->candles(10), ['timePeriod' => 8]);

        $this->assertEquals($candles[0], $ma->candle(timestamp: $candles[0]->t));
        $this->assertEquals($candles[1], $ma->candle(timestamp: $candles[1]->t));
        $this->assertEquals($candles[2], $ma->candle(timestamp: $candles[2]->t));
        $this->assertEquals($candles[3], $ma->candle(timestamp: $candles[3]->t));
        $this->assertEquals($candles[4], $ma->candle(timestamp: $candles[4]->t));
        $this->assertEquals($candles[5], $ma->candle(timestamp: $candles[5]->t));
        $this->assertEquals($candles[6], $ma->candle(timestamp: $candles[6]->t));
        $this->assertEquals($candles[7], $ma->candle(timestamp: $candles[7]->t));
        $this->assertEquals($candles[8], $ma->candle(timestamp: $candles[8]->t));
        $this->assertEquals($candles[9], $ma->candle(timestamp: $candles[9]->t));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Invalid candle timestamp.");
        $ma->candle(timestamp: $candles[9]->t + 1);
    }
}
