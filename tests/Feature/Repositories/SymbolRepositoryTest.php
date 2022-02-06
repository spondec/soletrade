<?php

namespace Tests\Feature\Repositories;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SymbolRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_next_candle()
    {
        $repo = $this->getRepo();
        $symbol = $this->getSymbol();

        DB::table('candles')->insert([
            ['symbol_id' => $symbol->id,
             'o'         => 1,
             'c'         => 2,
             'h'         => 3,
             'l'         => 4,
             'v'         => 5,
             't'         => time() * 1000],
            ['symbol_id' => $symbol->id,
             'o'         => 1,
             'c'         => 2.1,
             'h'         => 3,
             'l'         => 4,
             'v'         => 5,
             't'         => (time() + 1) * 1000]
        ]);

        $candles = DB::table('candles')
            ->where('symbol_id', $symbol->id)
            ->orderBy('t', 'ASC')
            ->limit(2)
            ->get();

        $this->assertCount(2, $candles);

        $nextCandle = $repo->assertNextCandle($symbol->id, $candles->first()->t);
        $this->assertEquals($candles->last(), $nextCandle);
        $nextCandle = $repo->assertNextCandle($symbol->id, $candles->last()->t - 1);
        $this->assertEquals($candles->last(), $nextCandle);
    }

    protected function getRepo(): SymbolRepository
    {
        return new SymbolRepository();
    }

    protected function getSymbol(): Symbol
    {
        Symbol::factory()->make([
            'symbol'   => $symbol = 'BTC/USDT',
            'interval' => $interval = '1h'])
            ->save();

        /** @var Symbol $symbol */
        $symbol = Symbol::query()
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->firstOrFail();
        return $symbol;
    }
}
