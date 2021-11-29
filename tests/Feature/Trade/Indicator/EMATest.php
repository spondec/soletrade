<?php

namespace Trade\Indicator;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\Indicator\EMA;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class EMATest extends TestCase
{
    public function test_ema()
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
            [EMA::class => ['timePeriod' => 8]]);

        $ema = $symbol->indicator(EMA::name());
        $this->assertIsFloat($ema->data()->first());
        $this->assertIsFloat($ema->data()->last());
        $this->assertCount(100 - 7, $ema->data());
        $this->assertEquals($candles->slice(0, 8)->last()->t, $ema->data()->keys()->first());
        $this->assertEquals($candles->last()->t, $ema->data()->keys()->last());
    }
}
