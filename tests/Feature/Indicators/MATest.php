<?php

namespace Tests\Feature\Indicators;

use App\Indicators\MA;
use App\Trade\Repository\SymbolRepository;

class MATest extends IndicatorTestCase
{
    public function test_sma()
    {
        $repo = new SymbolRepository();
        $symbol = $this->createCandles(100);

        $repo->initIndicators(
            $symbol,
            $candles = $symbol->candles(100),
            [MA::class => ['timePeriod' => 8]]
        );

        $sma = $symbol->indicator(MA::name());
        $this->assertIsFloat($sma->data()->first());
        $this->assertIsFloat($sma->data()->last());
        $this->assertCount(100 - 7, $sma->data());
        $this->assertEquals($candles->slice(0, 8)->last()->t, $sma->data()->keys()->first());
        $this->assertEquals($candles->last()->t, $sma->data()->keys()->last());
    }
}
