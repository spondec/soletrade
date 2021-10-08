<?php

namespace App\Trade;

use App\Models\Signal;

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

    public static function riskReward(bool   $isLong,
                                      float  $entry,
                                      float  $high,
                                      float  $low,
                                      ?float &$highRoi = null,
                                      ?float &$lowRoi = null): float
    {
        $side = $isLong ? Signal::BUY : Signal::SELL;

        if ($isLong)
        {
            $highRoi = self::roi($side, $entry, $high);
            $lowRoi = self::roi($side, $entry, $low);
        }
        else
        {
            $highRoi = self::roi($side, $entry, $low);
            $lowRoi = self::roi($side, $entry, $high);
        }

        if ($lowRoi == 0)
        {
            return 0;
        }

        return abs($highRoi / $lowRoi);
    }

    public static function roi(string $side, int|float $entryPrice, int|float $exitPrice): float
    {
        $roi = ($exitPrice - $entryPrice) * 100 / $entryPrice;

        if ($side === Signal::SELL)
        {
            $roi *= -1;
        }

        return round($roi, 2);
    }
}