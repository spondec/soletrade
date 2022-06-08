<?php

namespace Tests\Feature\Repositories;

use App\Models\Candle;
use App\Models\Symbol;
use App\Trade\Repository\SymbolRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SymbolRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_next_candle()
    {
        $repo = $this->symbolRepo();
        $symbol = Symbol::factory()
            ->count(1)
            ->create()
            ->first();

        $factory = Candle::factory();

        $candles = $factory->count(10)
            ->for($symbol)
            ->make();

        $i = 0;
        foreach ($candles as $candle) {
            $candles[0]->t = time() + $i++;
            $candle->save();
        }

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

    protected function symbolRepo(): SymbolRepository
    {
        return new SymbolRepository();
    }

    public function test_assert_lowest_highest_candle()
    {
        $repo = $this->symbolRepo();

        $symbol = Symbol::factory()
            ->count(1)
            ->create()
            ->first();

        $candles = Candle::factory()
            ->for($symbol)
            ->count(30)
            ->create();

        $middle = $candles->sortBy('t')->slice(10, 10);

        $oldest = $middle->first();
        $newest = $middle->last();

        $lowest = $middle->sortBy('l')->first();
        $highest = $middle->sortByDesc('h')->first();

        $pivots = $repo->assertLowestHighestCandle($symbol->id, $oldest->t, $newest->t);

        $this->assertEquals($highest->h, $pivots['highest']->h);
        $this->assertEquals($lowest->l, $pivots['lowest']->l);
    }
}
