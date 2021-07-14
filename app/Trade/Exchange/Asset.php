<?php

namespace App\Trade\Exchange;

class Asset
{
    public function __construct(protected string $asset,
                                protected float  $amount,
                                protected float  $available)
    {

    }

    public function name(): string
    {
        return $this->asset;
    }

    public function total(): float
    {
        return $this->amount;
    }

    public function available(): float
    {
        return $this->available;
    }
}