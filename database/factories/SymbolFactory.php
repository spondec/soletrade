<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Symbol>
 */
class SymbolFactory extends Factory
{
    protected $model = \App\Models\Symbol::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'exchange_id' => 1,
            'symbol'      => $this->faker->randomElement(['AAPL', 'MSFT', 'GOOG', 'AMZN', 'FB', 'TWTR', 'NFLX', 'TSLA', 'BABA', 'BA', 'BAC', 'C', 'CAT', 'CSCO', 'CVX', 'DIS', 'DOW', 'DUK', 'GE', 'HD', 'IBM', 'INTC', 'JNJ', 'JPM', 'KO', 'MCD', 'MMM', 'MRK', 'NKE', 'PEP', 'PG', 'T', 'UNH', 'UTX', 'V', 'VZ', 'WMT', 'XOM', 'YHOO']),
            'interval'    => $this->faker->randomElement(['1m', '5m', '15m', '30m', '1h', '4h', '1d']),
            'last_update' => $this->faker->dateTimeBetween('-1 years', 'now')->getTimestamp() * 1000,
        ];
    }
}
