<?php

namespace Tests\Feature\Repositories;

use App\Models\Candle;
use App\Models\Symbol;
use App\Trade\Repository\SymbolRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SymbolRepositoryTest extends TestCase
{
    use RefreshDatabase;

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
