<?php

namespace App\Trade\Exchange;

class OrderBook
{
    protected array $bid;
    protected array $ask;

    public function __construct(array $bid, array $ask)
    {
        //TODO:: sort first
        $this->bid = $bid;
        $this->ask = $ask;
    }
}