<?php

namespace App\Trade\Exchange\Spot;

use App\Trade\Exchange\Spot\Binance\BinanceSpot;

class Binance extends AbstractSpotExchange
{
    use BinanceSpot;

    protected function availableOrderActions(): array
    {
        return ['BUY', 'SELL'];
    }

    protected function accountType(): string
    {
        return 'SPOT';
    }

}