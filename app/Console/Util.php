<?php

namespace App\Console;

class Util
{
    public static function formatRoi(float $roi): string
    {
        $rounded = round($roi, 2);

        if ($roi > 0)
        {
            return "<fg=green>$rounded%</>";
        }

        if ($roi < 0)
        {
            return "<fg=red>$rounded%</>";
        }

        return "$rounded%";
    }

    public static function memoryUsage(): string
    {
        return (int)(memory_get_usage(true) / 1024 / 1024) . 'MB';
    }
}