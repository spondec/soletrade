<?php

namespace App\Trade;

enum Side: string
{
    case BUY = 'BUY';
    case SELL = 'SELL';

    public static function getExitSide(Side $side): Side
    {
        return match ($side)
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