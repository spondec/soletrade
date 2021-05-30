<?php

namespace App\Trade\Exchange;

class Asset
{
    public function __construct(protected string $symbol,
                                protected float $amount,
                                protected float $available)
    {

    }
}