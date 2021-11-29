<?php

namespace Trade\Indicator;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\Indicator\SMA;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class SMATest extends TestCase
{
    public function test_sma()
    {
        /** @var SymbolRepository $repo */
        $repo = App::make(SymbolRepository::class);

        /** @var Symbol $symbol */
        $symbol = Symbol::query()
            ->where('symbol', 'BTC/USDT')
            ->where('interval', '1h')
            ->firstOrFail();

        $repo->initIndicators($symbol,
            $candles = $symbol->candles(100),
            [SMA::class => ['timePeriod' => 8]]);

        $sma = $symbol->indicator(SMA::name());
        $this->assertIsFloat($sma->data()->first());
        $this->assertIsFloat($sma->data()->last());
        $this->assertCount(100 - 7, $sma->data());
        $this->assertEquals($candles->slice(0, 8)->last()->t, $sma->data()->keys()->first());
        $this->assertEquals($candles->last()->t, $sma->data()->keys()->last());
    }
}
