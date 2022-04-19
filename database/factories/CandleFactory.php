<?php

namespace Database\Factories;

use App\Trade\Calc;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Candle>
 */
class CandleFactory extends Factory
{
    protected $model = \App\Models\Candle::class;

    public function __construct($count = null,
                                ?Collection $states = null,
                                ?Collection $has = null,
                                ?Collection $for = null,
                                ?Collection $afterMaking = null,
                                ?Collection $afterCreating = null,
        $connection = null,

                                protected ?int $interval = null,
                                protected ?Carbon $startDate = null,
                                protected ?float $priceLowerThan = null,
                                protected ?float $priceHigherThan = null,
                                protected ?int $lastTimestamp = null)
    {
        parent::__construct($count, $states, $has, $for, $afterMaking, $afterCreating, $connection);
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $prices = [];
        for ($i = 0; $i < 4; $i++)
        {
            $prices[] = $this->randomPrice(2, 1, 100);
        }

        return [
            't' => $this->startDate && $this->interval
                ? $this->getNextTimestamp()
                : $this->faker
                    ->dateTimeBetween($this->startDate ?: '-1 year', 'now')
                    ->getTimestamp() * 1000,
            'o' => $prices[\array_rand($prices)],
            'c' => $prices[\array_rand($prices)],
            'h' => \max($prices),
            'l' => \min($prices),
            'v' => $this->faker->randomFloat(2, 100, 100 * 10),
        ];
    }

    /**
     * @param int $startDate
     * @param int $endDate
     * @param int $interval in seconds
     *
     * @return $this
     */
    public function fillBetween(int $startDate, int $endDate, int $interval): static
    {
        $this->interval = $interval * 1000;
        $this->startDate = Carbon::createFromTimestampMs($startDate = as_ms($startDate) - $this->interval);

        $endDate = as_ms($endDate);

        $this->count = 0;

        while ($startDate < $endDate)
        {
            $startDate += $this->interval;
            $this->count++;
        }
        return $this;
    }

    protected function newInstance(array $arguments = [])
    {
        //TODO:: dirty
        //When laravel min php requirement bumps to 8.1,
        //PR to remove the need of this via using
        //the array unpacking with string keys feature
        //at parent::newInstance()
        $arguments['interval'] = $this->interval;
        $arguments['startDate'] = $this->startDate;
        $arguments['priceLowerThan'] = $this->priceLowerThan;
        $arguments['priceHigherThan'] = $this->priceHigherThan;
        $arguments['lastTimestamp'] = $this->lastTimestamp;

        return parent::newInstance($arguments);
    }

    public function priceLowerThan(float $price): static
    {
        $this->priceLowerThan = $price - $price * 0.001;
        return $this;
    }

    public function priceHigherThan(float $price): static
    {
        $this->priceHigherThan = $price + $price * 0.001;
        return $this;
    }

    protected function randomPrice(int $maxDecimals, float $min, float $max): float|int
    {
        return $this->faker->randomFloat($maxDecimals,
            $this->priceHigherThan ?? $min,
            $this->priceLowerThan ?? $max);
    }

    protected function getNextTimestamp(): int
    {
        if (!$this->lastTimestamp)
        {
            $this->lastTimestamp = $this->startDate->getTimestampMs();
        }
        return $this->lastTimestamp += $this->interval;
    }
}
