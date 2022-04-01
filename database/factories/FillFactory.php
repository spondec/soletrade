<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fill>
 */
class FillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'timestamp'        => $this->faker->dateTimeBetween('-1 year', 'now')->getTimestamp() * 1000,
            'quantity'         => $size = $this->faker->randomFloat(2, 0.01, 100),
            'price'            => $price = $this->faker->randomFloat(2, 0.01, 100),
            'commission_asset' => 'USDT',
            'commission'       => $price * $size * 0.001,
            'trade_id'         => $this->faker->randomNumber(),
        ];
    }
}
