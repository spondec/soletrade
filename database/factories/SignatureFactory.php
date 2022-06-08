<?php

namespace Database\Factories;

use App\Trade\HasSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Signature>
 */
class SignatureFactory extends Factory
{
    use HasSignature;

    protected $model = \App\Models\Signature::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'data' => $data = $this->faker->shuffleArray($this->faker->words(100)),
            'hash' => $this->hash($data)
        ];
    }

    public function data(array $data)
    {
        return $this->state(fn (array $attributes) => [
            'data' => $data,
            'hash' => $this->hash($data)
        ]);
    }
}
