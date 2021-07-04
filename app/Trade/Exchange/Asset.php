<?php

namespace App\Trade\Exchange;

class Asset
{
    public function __construct(protected string $symbol,
                                protected float  $amount,
                                protected float  $available)
    {

    }

    public function symbol(): string
    {
        return $this->symbol;
    }

    public function total(): float
    {
        return $this->amount;
    }

    public function available(): float
    {
        return $this->available;
    }

    public function currencyValue(string $currency): float
    {

    }
}