<?php

namespace Database\Factories;

use App\Models\TradeSetup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TradeSetup>
 */
class TradeSetupFactory extends Factory
{
    protected $model = TradeSetup::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'signal_count' => 0,
            'timestamp'    => $timestamp = $this->faker->dateTimeBetween('-1 year', 'now')->getTimestamp() * 1000,
            'price_date'   => $this->faker->dateTimeBetween(Carbon::createFromTimestampMs($timestamp))->getTimestamp() * 1000,
            'name'         => $this->faker->name,
            'side'         => $this->faker->randomElement(['BUY', 'SELL']),
            'price'        => $this->faker->randomFloat(2, 0, 100),
            'size'         => 100,
        ];
    }

    public function size(float $size)
    {
        return $this->state([
            'size' => $size,
        ]);
    }

    public function price(float $price)
    {
        return $this->state([
            'price' => $price,
        ]);
    }

    public function stopPrice(?float $price = null)
    {
        return $this->state(fn(array $attributes) => [
            'stop_price' => $price ?? $this->faker->randomFloat(2, 0, 100),
        ]);
    }

    public function targetPrice(?float $price = null)
    {
        return $this->state(fn(array $attributes) => [
            'close_price' => $price ?? $this->faker->randomFloat(2, 0, 100),
        ]);
    }
}
