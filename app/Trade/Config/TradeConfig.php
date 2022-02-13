<?php

namespace App\Trade\Config;

use App\Models\Signature;

class TradeConfig extends Config
{
    public readonly bool $withSignals;

    public function __construct(public readonly array $signals,
                                public readonly \Closure $callback,
                                public readonly Signature $signature)
    {
        $this->withSignals = !empty($signals);
    }
}