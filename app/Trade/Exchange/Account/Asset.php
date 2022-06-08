<?php

namespace App\Trade\Exchange\Account;

class Asset
{
    /**
     * The properties will be updated as the associated balance is updated.
     *
     * @param string $name
     * @param float  $total
     * @param float  $available
     *
     * @see \App\Trade\Exchange\Fetcher::registerBalanceListeners()
     */
    public function __construct(public readonly string $name,
                                private float $total,
                                private float $available)
    {
    }

    public function total(): float
    {
        return $this->total;
    }

    public function available(): float
    {
        return $this->available;
    }
}
