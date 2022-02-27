<?php

namespace App\Trade\Exchange\Binance\Spot;

use App\Trade\Exchange\Exchange;

class Binance extends Exchange
{
    protected function setup(): void
    {
        $api = new \ccxt\binance([
            'apiKey'  => $this->apiKey,
            'secret'  => $this->secretKey,
            'options' => [
                'recvWindow' => 5000
            ]
        ]);

        $this->fetch = new Fetcher($this, $api);
        $this->order = new Orderer($this, $api);
    }
}