<?php

declare(strict_types=1);

namespace App\Trade;

class TradeAsset
{
    public function __construct(public readonly AllocatedAsset $allocation)
    {

    }

    public function registerRoi(float $roi)
    {
        $amount = $this->allocation->amount();
        $this->allocation->allocate($amount - Calc::pnl($amount, $roi));
    }

    public function getProportionalSize(float $realSize): float
    {
        return $this->allocation->getProportionalSize($realSize);
    }

    public function getRealSize(float $proportionalSize): float
    {
        return $this->allocation->getRealSize($proportionalSize);
    }

    public function quantity(float $price, float $proportionalSize): float
    {
        return $this->getRealSize($proportionalSize) / $price;
    }
}