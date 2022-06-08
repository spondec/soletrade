<?php

namespace Tests\Feature\Trade\Indicator;

use App\Indicators\MA;
use Tests\Feature\Indicators\IndicatorTestCase;

class IndicatorTest extends IndicatorTestCase
{
    public function test_scan_without_signal_callback()
    {
        $symbol = $this->createCandles(10);
        $sma = new MA($symbol, $candles = $symbol->candles(10), ['timePeriod' => 8]);

        $data = $sma->data();
        $iterator = $data->getIterator();
        $slice = $candles->slice(7);
        $candleIterator = $slice->getIterator();

        foreach ($sma->scan() as $result) {
            $this->assertEquals($iterator->key(), $result['timestamp']);
            $this->assertEquals($sma->current(), $iterator->current());
            $this->assertEquals($sma->candle(), $candleIterator->current());
            $candleIterator->next();
            $iterator->next();
        }
    }
}
