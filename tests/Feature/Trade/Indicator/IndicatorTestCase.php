<?php

namespace Tests\Feature\Trade\Indicator;

use App\Models\Candle;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

abstract class IndicatorTestCase extends TestCase
{
    use DatabaseTransactions;

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