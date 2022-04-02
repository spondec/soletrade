<?php

namespace App\Trade\Exchange\FTX;

use App\Trade\Exchange\Exchange;

class FTX extends Exchange
{
    protected function setup(): void
    {
        $api = new \ccxt\ftx([
            'apiKey'  => $this->apiKey,
            'secret'  => $this->secretKey,
            'headers' => [
                'FTX-SUBACCOUNT' => $this->config['subaccount']
                    ?? throw new \LogicException('Missing FTX sub account.'),
            ]
        ]);

        $this->fetch = new Fetcher($this, $api);
    }
}