<?php

namespace App\Trade\Strategy\Optimization\Parameter;

class RangedSet extends ParameterSet
{
    public function __construct(public readonly int|float $min,
                                public readonly int|float $max,
                                public readonly int|float $step)
    {
        if ($min > $max)
        {
            throw new \InvalidArgumentException('Range minimum cannot be greater than the maximum.');
        }
    }

    public function values(): array
    {
        $parameters = [];

        foreach ($this->iterator() as $param)
        {
            $parameters[] = $param;
        }

        return $parameters;
    }

    public function iterator(): \Iterator
    {
        return $this->range($this->min, $this->max, $this->step);
    }

    protected function range(float|int $start, float|int $end, float|int $step): \Generator
    {
        if ($start <= $end)
        {
            if ($step <= 0)
            {
                throw new \LogicException('Step must be positive.');
            }

            for ($i = $start; $i <= $end; $i += $step)
            {
                yield $i;
            }
        }
        else
        {
            if ($step >= 0)
            {
                throw new \LogicException('Step must be negative.');
            }

            for ($i = $start; $i >= $end; $i += $step)
            {
                yield $i;
            }
        }
    }

    public function count(): int
    {
        return (int)(($this->max - $this->min) / $this->step);
    }
}