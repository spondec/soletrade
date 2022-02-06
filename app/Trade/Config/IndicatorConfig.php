<?php

namespace App\Trade\Config;

class IndicatorConfig extends Config
{
    public function __construct(public readonly string $class,
                                public readonly array $config,
                                public readonly \Closure $callback)
    {
    }
}