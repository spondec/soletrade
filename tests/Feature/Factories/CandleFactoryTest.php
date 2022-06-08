<?php

namespace Tests\Feature\Factories;

use App\Models\Symbol;
use Database\Factories\CandleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandleFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fill_between()
    {
        $time = time();
        $interval = 60;
        $symbol = Symbol::factory()->create();

        $factory = $this->candleFactory();
        $candles = $factory
            ->for($symbol)
            ->fillBetween($time - 600, $time, $interval)
            ->create();

        $this->assertEquals($time - 600, $candles->first()->t / 1000);
        $this->assertEquals($time, $candles->last()->t / 1000);

        $prev = $candles->shift();
        $iterator = $candles->getIterator();

        while ($iterator->valid())
        {
            $this->assertEquals($interval * 1000, $iterator->current()->t - $prev->t);

            $prev = $iterator->current();
            $iterator->next();
        }
    }

    protected function candleFactory(): CandleFactory
    {
        return new CandleFactory();
    }

    public function test_price_higher_lower_than()
    {
        $candles = $this->candleFactory()
            ->for(Symbol::factory()->create())
            ->priceHigherThan(8)
            ->priceLowerThan(10)
            ->count(4)
            ->create();

        foreach ($candles as $candle)
        {
            $this->assertTrue(in_array((int)((string)$candle->c)[0], [8, 9]));
            $this->assertTrue(in_array((int)((string)$candle->o)[0], [8, 9]));
            $this->assertTrue(in_array((int)((string)$candle->h)[0], [8, 9]));
            $this->assertTrue(in_array((int)((string)$candle->l)[0], [8, 9]));
        }
    }
}
