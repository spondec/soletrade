<?php

namespace App\Trade\Exchange\Futures;

use App\Models\Position;

interface IExchange extends \App\Trade\Exchange\IExchange
{
    /**
     * @return Position[]
     *
     */
    public function getOpenPositions(): array;

    public function getMarkPrice(string $symbol): float;
}