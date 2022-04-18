<?php

namespace Tests\Feature\Indicators;

use App\Indicators\EMA;
use App\Repositories\SymbolRepository;

class EMATest extends IndicatorTestCase
{
    public function test_ema()
    {
        $repo = new SymbolRepository();

        $symbol = $this->createCandles(100);

        $repo->initIndicators($symbol,
            $candles = $symbol->candles(100),
            [EMA::class => ['timePeriod' => 8]]);

        $ema = $symbol->indicator(EMA::name());
        $this->assertIsFloat($ema->data()->first());
        $this->assertIsFloat($ema->data()->last());
        $this->assertCount(100 - 7, $ema->data());
        $this->assertEquals($candles->slice(0, 8)->last()->t, $ema->data()->keys()->first());
        $this->assertEquals($candles->last()->t, $ema->data()->keys()->last());
    }
}
