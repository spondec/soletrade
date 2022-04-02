<?php

namespace App\Trade\Exchange;

class OrderBook
{
    public readonly float $initTime;
    public float $timeout;

    /**
     * @param float[] $bids
     * @param float[] $asks
     */
    public function __construct(protected string $symbol,
                                protected array  $bids,
                                protected array  $asks)
    {
        $this->initTime = \microtime(true);

        if (!$this->bids || !$this->asks)
        {
            throw new \App\Exceptions\EmptyOrderBookException("Order book data is empty for $symbol.");
        }

        $this->assertPositiveSpread();
    }

    protected function assertPositiveSpread(): void
    {
        if ($this->spread() < 0)
        {
            throw new \LogicException("Spread can't be negative.");
        }
    }

    public function isExpired()
    {
        return \microtime(true) - $this->initTime >= $this->timeout;
    }

    public function bestBid(): float
    {
        return \max($this->bids);
    }

    public function spread(): float
    {
        return $this->bestAsk() - $this->bestBid();
    }

    public function bestAsk(): float
    {
        return \min($this->asks);
    }

    protected final function avg(array $values): float
    {
        return \array_sum($values) / \count($values);
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