<?php

namespace App\Trade;

final class Enum
{
    private function __construct()
    {
    }

    public static function case(\UnitEnum $enum): string|int
    {
        return $enum->value ?? $enum->name;
    }

    /**
     * @return string[]|int[]
     */
    public static function cases(string|\UnitEnum $class): array
    {
        return \array_map(static function (\UnitEnum $enum): string {
            return static::case($enum);
        }, $class::cases());
    }
}
