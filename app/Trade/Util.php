<?php

namespace App\Trade;

use App\Models\Symbol;
use App\Trade\Collection\CandleCollection;
use Carbon\Carbon;

class Util
{
    public static function dateFormat(int $timestamp, string $format = 'Y-m-d'): string
    {
        return Carbon::createFromTimestampMs($timestamp)->format($format);
    }

    public static function formatRoi(float $roi): string
    {
        $rounded = \round($roi, 2);

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
        return (int) (\memory_get_usage(true) / 1024 / 1024) . 'MB';
    }

    public static function varExport(mixed $expression): string
    {
        $export = \var_export($expression, true);
        $export = \preg_replace('/^([ ]*)(.*)/m', '$1$1$2', $export);
        $array = \preg_split("/\r\n|\n|\r/", $export);
        $array = \preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = \implode(PHP_EOL, \array_filter(['['] + $array));

        return $export;
    }

    public static function indicatorConfig(string $indicator): array
    {
        return (new (INDICATOR_NAMESPACE . $indicator)(new Symbol(), new CandleCollection()))->config();
    }

    public static function getDuplicates(array $array): array
    {
        return \array_diff_assoc($array, \array_unique($array));
    }
}
