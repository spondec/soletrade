<?php

namespace App\Trade\Config;

use App\Models\Signature;
use App\Models\Symbol;

class TradeConfig extends Config
{
    public function __construct(public readonly array $signals,
                                public readonly \Closure $callback,
                                public readonly Signature $signature,
                                public readonly Symbol $symbol)
    {
    }
}