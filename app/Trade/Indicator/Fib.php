<?php

namespace App\Trade\Indicator;

use App\Models\Signature;

class Fib extends AbstractIndicator
{
    public array $prevFib;
    protected array $config = [
        'period'              => 144,
        'distanceToLevel'     => 1,
        'totalBarsAfterLevel' => 3,
        'levels'              => [236, 382, 500, 618, 786]
    ];

    public function nearestFib(array $levels, float $price): array
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

        return $this->prevFib = [
            'level'    => $fibLevel,
            'price'    => $fibPrice,
            'distance' => $minDistance / $price * 100
        ];
    }

    public function raw(): array
    {
        $raw = [];

        foreach ($this->data as $timestamp => $fibLevels)
        {
            foreach ($fibLevels as $key => $val)
            {
                $raw[$key][$timestamp] = $val;
            }
        }

        return $raw;
    }

    protected function getBindValue(string|int $bind, ?int $timestamp = null): float
    {
        return $this->data[$this->current][$bind];
    }

    protected function getBindable(): array
    {
        return [0, 236, 382, 500, 618, 702, 786, 886, 1000];
    }

    protected function getBindPrice(mixed $bind): float
    {
        return $this->data[$this->current][$bind];
    }

    protected function run(): array
    {
        $fib = [];
        $period = (int)$this->config['period'];
        $highs = [];
        $lows = [];
        $bars = 0;
        $levels = $this->config['levels'];
        sort($levels);
        foreach ($this->candles as $candle)
        {
            $highs[] = (float)$candle->h;
            $lows[] = (float)$candle->l;

            if ($bars === $period)
            {
                $highest = max($highs);
                $lowest = min($lows);

                $keys = array_keys($highs, $highest);
                $highestPosition = (int)end($keys);

                $keys = array_keys($lows, $lowest);
                $lowestPosition = (int)end($keys);
                $new = [];

                if ($lowestPosition < $highestPosition)
                {
                    $new[0] = $highest;
                    foreach ($levels as $level)
                    {
                        $new[$level] = $highest - ($highest - $lowest) * ($level / 1000);
                    }
                    $new[1000] = $highest - ($highest - $lowest) * 1.000;
                }
                else
                {
                    $new[0] = $lowest;
                    foreach ($levels as $level)
                    {
                        $new[$level] = ($highest - $lowest) * ($level / 1000) + $lowest;
                    }
                    $new[1000] = ($highest - $lowest) * 1.000 + $lowest;
                }

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

    public function buildSignalName(array $params): string
    {
        return 'FIB-' . $params['side'] . '_' . $params['level'];
    }

    protected function getSavePoints(int|string $bind, Signature $signature): array
    {
        $points = [];

        foreach ($this->data as $timestamp => $fib)
        {
            $points[] = [
                'timestamp' => $timestamp,
                'value'     => $fib[$bind]
            ];
        }

        return $points;
    }
}