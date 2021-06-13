<?php

namespace App\Trade\Setup;

class TakeProfit
{
    public function __construct(protected string $symbol, protected float $price, protected int $percent)
    {
        if ($percent <= 0 || $percent >= 100)
        {
            throw new \LogicException('Argument $percent should be between 1 and 100.');
        }
    }

    public function price(): float
    {
        return $this->price;
    }

    public function percent(): int
    {
        return $this->percent;
    }
}