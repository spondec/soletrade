<?php

namespace App\Trade\Exchange;

class OrderBook
{
    protected float $initTime;

    const TIMEOUT = 0.1;

    /**
     * @param float[] $bids
     * @param float[] $asks
     */
    public function __construct(protected array $bids, protected array $asks)
    {
        $this->initTime = microtime(true);
        $this->assertSpreadPositive();
    }

    protected function assertSpreadPositive(): void
    {
        if ($this->spread() < 0)
        {
            throw new \LogicException("Spread can't be negative.");
        }
    }

    public function isExpired()
    {
        return microtime(true) - $this->initTime >= self::TIMEOUT;
    }

    public function bestBid(): float
    {
        return max($this->bids);
    }

    public function spread(): float
    {
        return $this->bestAsk() - $this->bestBid();
    }

    public function bestAsk(): float
    {
        return min($this->asks);
    }

    protected final function avg(array $values): float
    {
        return array_sum($values) / count($values);
    }

    public function averageBid(): float
    {
        return $this->avg($this->bids);
    }

    public function averageAsk(): float
    {
        return $this->avg($this->asks);
    }
}