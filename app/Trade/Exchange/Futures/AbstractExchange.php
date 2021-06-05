<?php

namespace App\Trade\Exchange\Futures;

use App\Models\Position;

abstract class AbstractExchange extends \App\Trade\Exchange\AbstractExchange
{
    /**
     * @return Position[]
     *
     */
    abstract public function getOpenPositions(): array;

    abstract public function getMarkPrice(string $symbol): float;
}