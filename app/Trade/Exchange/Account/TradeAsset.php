<?php

declare(strict_types=1);

namespace App\Trade\Exchange\Account;

use App\Trade\Calc;

class TradeAsset
{
    public function __construct(public readonly AllocatedAsset $allocation)
    {

    }

    public function registerRoi(float $roi): void
    {
        $amount = $this->allocation->amount() / $this->allocation->leverage;
        $this->allocation->allocate($amount + Calc::pnl($amount, $roi));
    }

    public function proportional(float $realSize): float
    {
        return $this->allocation->getProportionalSize($realSize);
    }

    public function real(float $proportionalSize): float
    {
        return $this->allocation->getRealSize($proportionalSize);
    }

    public function quantity(float $price, float $proportionalSize): float
    {
        return $this->real($proportionalSize) / $price;
    }
}