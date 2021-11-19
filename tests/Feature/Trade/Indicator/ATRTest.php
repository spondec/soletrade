<?php

namespace Tests\Feature\Trade\Indicator;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\Indicator\ATR;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ATRTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_atr()
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
            [ATR::class => ['timePeriod' => 14]]);

        $atr = $symbol->indicator(ATR::name());
        $this->assertIsFloat($atr->data()->first());
        $this->assertIsFloat($atr->data()->last());
        $this->assertCount(100 - 14, $atr->data());
        $this->assertEquals($candles->slice(0, 15)->last()->t, $atr->data()->keys()->first());
        $this->assertEquals($candles->last()->t, $atr->data()->keys()->last());
    }
}

