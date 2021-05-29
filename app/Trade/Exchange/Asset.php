<?php

namespace App\Trade\Exchange;

class Asset
{
    protected string $symbol;
    protected float $amount;

    public function __construct(string $symbol, float $amount)
    {
        $this->symbol = $symbol;
        $this->amount = $amount;
    }
}