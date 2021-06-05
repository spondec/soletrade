<?php

namespace App\Trade\Exchange;

use App\Trade\Strategy\AbstractStrategy;

abstract class AbstractTrader
{
    protected AbstractExchange $exchange;
    protected AbstractStrategy $strategy;

    public function __construct(AbstractExchange $exchange, AbstractStrategy $strategy)
    {
        $this->exchange = $exchange;
        $this->strategy = $strategy;
    }

    public function loop()
    {

    }
}