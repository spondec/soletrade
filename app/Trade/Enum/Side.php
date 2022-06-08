<?php

namespace App\Trade\Enum;

enum Side: string
{
    case BUY = 'BUY';
    case SELL = 'SELL';

    public function opposite(): Side
    {
        return match ($this) {
            static::BUY  => static::SELL,
            static::SELL => static::BUY
        };
    }

    public function isBuy(): bool
    {
        return $this == static::BUY;
    }
}
