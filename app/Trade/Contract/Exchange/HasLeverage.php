<?php

namespace App\Trade\Contract\Exchange;

interface HasLeverage
{
    public function setLeverage(float $leverage = 1, ?string $symbol = null): void;
}