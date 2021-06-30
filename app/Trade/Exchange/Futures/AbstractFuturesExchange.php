<?php

namespace App\Trade\Exchange\Futures;

use App\Models\Position;
use App\Trade\Exchange\AbstractExchange;

abstract class AbstractFuturesExchange extends AbstractExchange
{
    /**
     * @return Position[]
     *
     */
    abstract public function openPositions(): array;

    abstract public function markPrice(string $symbol): float;

    public function short()
    {
        $this->order->side = 'SHORT';
        return $this;
    }

    public function long()
    {
        $this->order->side = 'LONG';
        return $this;
    }
}