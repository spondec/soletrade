<?php

namespace App\Trade\Exchange\FTX;

use App\Trade\Contract\Exchange\HasLeverage;
use App\Trade\Exchange\Exchange;

class FTX extends Exchange implements HasLeverage
{
    protected \ccxt\ftx $api;

    protected function setup(): void
    {
        $this->api = new \ccxt\ftx([
            'apiKey'  => $this->apiKey,
            'secret'  => $this->secretKey,
            'headers' => [
                'FTX-SUBACCOUNT' => $this->config['subaccount']
                    ?? throw new \LogicException('Missing FTX sub account.'),
            ]
        ]);

        $this->fetch = new Fetcher($this, $this->api);
        $this->order = new Orderer($this, $this->api);
    }

    public function setLeverage(float $leverage = 1, ?string $symbol = null): void
    {
        $this->api->set_leverage($leverage, $symbol);
    }
}