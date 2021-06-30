<?php

namespace App\Trade\Exchange;

class OrderBook
{
    protected array $bid;
    protected array $ask;

    /**
     * @param float[] $bid
     * @param float[] $ask
     */
    public function __construct(array $bid, array $ask)
    {
        //TODO:: sort first
        $this->bid = $bid;
        $this->ask = $ask;
    }

    public function bestBid(): float
    {

    }

    public function bestAsk(): float
    {

    }

    public function averageBid()
    {

    }

    public function averageAsk()
    {

    }
}