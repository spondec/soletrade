<?php

namespace Tests\Feature\Indicators;

use App\Models\Candle;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class IndicatorTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createCandles(int $count): Symbol
    {
        Candle::factory()
            ->for(Symbol::factory())
            ->count($count)
            ->create();

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Symbol::query()->firstOrFail();
    }
}
