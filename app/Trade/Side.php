<?php

namespace App\Trade;

enum Side: string
{
    case BUY = 'BUY';
    case SELL = 'SELL';

    public function opposite(): Side
    {
        return match ($this)
        {
            self::BUY => self::SELL,
            self::SELL => self::BUY
        };
    }

    public function isBuy(): bool
    {
        return $this == self::BUY;
    }
}