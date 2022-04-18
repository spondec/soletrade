<?php

namespace Tests\Feature\Indicators;

use App\Indicators\ATR;
use App\Repositories\SymbolRepository;

class ATRTest extends IndicatorTestCase
{
    public function test_atr()
    {
        $repo = new SymbolRepository();

        $symbol = $this->createCandles(100);

        $repo->initIndicators($symbol,
            $candles = $symbol->candles(100),
            [ATR::class => ['timePeriod' => 14]]);

        $this->assertCount(100, $candles);

        $atr = $symbol->indicator(ATR::name());
        $this->assertIsFloat($atr->data()->first());
        $this->assertIsFloat($atr->data()->last());
        $this->assertCount(100 - 14, $atr->data());
        $this->assertEquals($candles->slice(0, 15)->last()->t, $atr->data()->keys()->first());
        $this->assertEquals($candles->last()->t, $atr->data()->keys()->last());
    }
}

