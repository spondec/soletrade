<?php

namespace Tests\Feature\Repositories;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SymbolRepositoryTest extends TestCase
{

    public function test_fetch_next_candle()
    {
        $repo = $this->getRepo();

        /** @var Symbol $symbol */
        $symbol = Symbol::query()
            ->where('symbol', 'BTC/USDT')
            ->where('interval', '1h')
            ->firstOrFail();

        $candles = DB::table('candles')
            ->where('symbol_id', $symbol->id)
            ->orderBy('t', 'ASC')
            ->limit(2)
            ->get();

        $this->assertCount(2, $candles);

        $nextCandle = $repo->assertNextCandle($symbol->id, $candles->first()->t);
        $this->assertEquals($candles->last()->t, $nextCandle->t);
        $nextCandle = $repo->assertNextCandle($symbol->id, $candles->last()->t - 1);
        $this->assertEquals($candles->last()->t, $nextCandle->t);
    }

    public function test_fetch_lower_interval_candles()
    {
        $repo = $this->getRepo();
        /** @var Symbol $symbol */
        $symbol = Symbol::query()
            ->where('interval', '1h')
            ->where('symbol', 'BTC/USDT')
            ->firstOrFail();
        $candles = $symbol->candles(2, time());

        $lowerIntervalCandles = $repo->fetchLowerIntervalCandles($candles->first(), $symbol, '15m');

        /** @var Symbol $lowerIntervalSymbol */
        $lowerIntervalSymbol = Symbol::query()
            ->where('interval', '15m')
            ->where('symbol', 'BTC/USDT')
            ->firstOrFail();

        $this->assertCount(5, $lowerIntervalCandles);

        foreach ($lowerIntervalCandles as $c)
        {
            $this->assertTrue(Calc::inRange($c->t, $candles->last()->t, $candles->first()->t));
        }

        $this->assertEquals($candles->last()->t, $lowerIntervalCandles->last()->t);
        $this->assertEquals($candles->first()->t, $lowerIntervalCandles->first()->t);
        $this->assertEquals($lowerIntervalSymbol->id, $lowerIntervalCandles->first()->symbol_id);
    }

    protected function getRepo(): SymbolRepository
    {
        return App::make(SymbolRepository::class);
    }
}
