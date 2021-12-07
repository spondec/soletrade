<?php

namespace App\Trade\Indicator;

use App\Trade\CandleCollection;
use JetBrains\PhpStorm\ArrayShape;

class Fib extends AbstractIndicator
{
    protected array $config = [
        'period' => 144,
        'levels' => [236, 382, 500, 618, 786]
    ];

    protected bool $isProgressive = true;
    protected bool $recalculate = false;

    /**
     * Upward means the retracement goes from low to high.
     */
    public static function isUpward(array $prices): bool
    {
        $firstLevel = array_key_first($prices);
        $firstLevelPrice = $prices[$firstLevel];

        foreach ($prices as $level => $price)
        {
            if ($level !== $firstLevel)
            {
                return $price < $firstLevelPrice;
            }
        }

        throw new \InvalidArgumentException('Invalid price and fib levels provided as argument.');
    }

    /**
     * @param array $prices - Keys should be the corresponding fib level of each price.
     */
    public static function targetLevels(array $prices, int $level, bool $isBuy): array
    {
        $levels = array_keys($prices);
        $isUpward = self::isUpward($prices);

        if ($isUpward)
        {
            if ($isBuy)
            {
                $target = array_reverse(array_filter($levels, static fn(float $l) => $l < $level));
            }
            else
            {
                $target = array_filter($levels, static fn(float $l) => $l > $level);
            }
        }
        else
        {
            if ($isBuy)
            {
                $target = array_filter($levels, static fn(float $l) => $l > $level);
            }
            else
            {
                $target = array_reverse(array_filter($levels, static fn(float $l) => $l < $level));
            }
        }

        return array_values($target);
    }

    #[ArrayShape(['level' => "int|string", 'price' => "float", 'distance' => "float|int"])]
    public static function nearestLevel(array $levels, float $price): array
    {
        $minDistance = null;
        foreach ($levels as $level => $levelPrice)
        {
            $distance = abs($price - $levelPrice);
            if (!$minDistance || $distance < $minDistance)
            {
                $minDistance = $distance;
                $fibLevel = $level;
                $fibPrice = $levelPrice;
            }
        }

        return [
            'level'    => $fibLevel,
            'price'    => $fibPrice,
            'distance' => $minDistance / $price * 100
        ];
    }

    public function raw(): array
    {
        $raw = [];

        foreach ($this->data() as $timestamp => $fibLevels)
        {
            foreach ($fibLevels as $key => $val)
            {
                $raw[$key][$timestamp] = $val;
            }
        }

        return $raw;
    }

    public function buildSignalName(array $params): string
    {
        return 'FIB-' . $params['side'] . '_' . $params['level'];
    }

    protected function getBind(int|string $bind, mixed $value): mixed
    {
        return $value[$bind];
    }

    protected function getBindable(): array
    {
        return array_filter($this->config['levels'], static fn(int $level): bool => !in_array($level, [0, 1000]));
    }

    protected function getBindPrice(mixed $bind): float
    {
        return $this->current()[$bind];
    }

    protected function setup(): void
    {
        $levels = &$this->config['levels'];

        $levels[] = 0;
        $levels[] = 1000;

        $levels = array_unique($levels);
        sort($levels);
    }

    protected function calculate(CandleCollection $candles): array
    {
        $levels = $this->config['levels'];
        $period = (int)$this->config['period'];

        $fib = [];
        $highs = [];
        $lows = [];
        $bars = 0;

        foreach ($candles as $candle)
        {
            $highs[] = (float)$candle->h;
            $lows[] = (float)$candle->l;

            if ($bars === $period)
            {
                $highest = max($highs);
                $lowest = min($lows);

                $new = [];

                $new[0] = $highest;
                foreach ($levels as $level)
                {
                    $new[$level] = $highest - ($highest - $lowest) * ($level / 1000);
                }
                $new[1000] = $highest - ($highest - $lowest) * 1.000;

                $fib[] = $new;

                array_shift($highs);
                array_shift($lows);
            }
            else
            {
                $bars++;
            }
        }

        return $fib;
    }
}