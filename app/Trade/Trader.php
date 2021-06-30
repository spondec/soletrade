<?php

namespace App\Trade;

use App\Trade\Exchange\Futures\AbstractFuturesExchange;
use App\Trade\Exchange\Spot\AbstractSpotExchange;
use App\Trade\Strategy\AbstractStrategy;

class Trader
{
    protected PositionManager $manager;

    public function __construct(
        protected AbstractStrategy        $strategy,
        protected AbstractSpotExchange    $spot,
        protected AbstractFuturesExchange $futures)
    {
        $this->manager = PositionManager::instance();
    }
}