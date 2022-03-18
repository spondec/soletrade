<?php

namespace App\Trade;

final class Enum
{
    private function __construct()
    {
    }

    /**
     * @param string|\UnitEnum $class
     *
     * @return string[]
     */
    public static function cases(string|\UnitEnum $class): array
    {
        return \array_map(static fn(\UnitEnum $enum): string => $enum->value ?? $enum->name, $class::cases());
    }
}