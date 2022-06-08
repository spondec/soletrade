<?php

namespace Database\Factories;

use App\Trade\Enum;
use App\Trade\Enum\OrderStatus;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'exchange_id'       => 1,
            'reduce_only'       => false,
            'status'            => $this->faker->randomElement(Enum::cases(OrderStatus::class)),
            'symbol'            => $this->faker->randomElement(['BTC/USDT', 'ETH/USDT']),
            'type'              => $this->faker->randomElement(Enum::cases(OrderType::class)),
            'side'              => $this->faker->randomElement(Enum::cases(Side::class)),
            'quantity'          => $this->faker->randomFloat(2, 0.01, 100),
            'filled'            => $this->faker->randomFloat(2, 0.01, 100),
            'price'             => $this->faker->randomFloat(2, 0.01, 100),
            'stop_price'        => null,
            'commission'        => $this->faker->randomFloat(2, 0.01, 100),
            'commission_asset'  => 'USDT',
            'exchange_order_id' => $this->faker->uuid,
        ];
    }
}
