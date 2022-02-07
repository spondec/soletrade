<?php

namespace App\Trade\Exchange\Spot;

class BinanceSpot extends AbstractSpotExchange
{
    use \App\Trade\Exchange\Spot\Binance\BinanceSpot;

    protected function availableOrderActions(): array
    {
        return ['BUY', 'SELL'];
    }

    protected function accountType(): string
    {
        return 'SPOT';
    }
}