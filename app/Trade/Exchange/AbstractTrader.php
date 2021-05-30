<?php

namespace App\Trade\Exchange;

use App\Trade\Strategy\AbstractStrategy;

abstract class AbstractTrader
{
    protected IExchange $exchange;
    protected AbstractStrategy $strategy;

    public function __construct(IExchange $exchange, AbstractStrategy $strategy)
    {
        $this->exchange = $exchange;
        $this->strategy = $strategy;
    }

    public function loop()
    {

    }
}