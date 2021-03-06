<?php

declare(strict_types=1);

namespace App\Trade;

class Calc
{
    public static function pnl(float $balance, float $roi): float|int
    {
        return $balance * $roi / 100;
    }

    public static function inRange(float $value, float $high, float $low): bool
    {
        return $value <= $high && $value >= $low;
    }

    public static function realizePrice(bool $isBuy, float $price, float $high, float $low): float|false
    {
        if (static::inRange($price, $high, $low))
        {
            return $price;
        }

        // Look for a better price outside the range
        if ($isBuy)
        {
            if ($high < $price)
            {
                return $high;
            }
            return false;
        }

        if ($low > $price)
        {
            return $low;
        }

        return false;
    }

    public static function roi(bool $isBuy, int|float $entryPrice, int|float $exitPrice): float
    {
        $roi = ($exitPrice - $entryPrice) * 100 / $entryPrice;

        if (!$isBuy)
        {
            $roi *= -1;
        }

        return $roi;
    }

    public static function duration(string $interval): int
    {
        return \ccxt\Exchange::parse_timeframe($interval);
    }

    public static function avg(array $numbers): float
    {
        return \array_sum($numbers) / \count($numbers);
    }
}