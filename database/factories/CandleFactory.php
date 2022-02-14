<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CandleFactory extends Factory
{
    protected $model = \App\Models\Candle::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            't' => $this->faker->dateTimeBetween('-1 year', 'now')->getTimestamp() * 1000,
            'o' => $this->faker->randomFloat(2, 0, 100),
            'c' => $this->faker->randomFloat(2, 0, 100),
            'h' => $this->faker->randomFloat(2, 0, 100),
            'l' => $this->faker->randomFloat(2, 0, 100),
            'v' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
