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
    abstract public function getOpenPositions(): array;

    abstract public function getMarkPrice(string $symbol): float;
}