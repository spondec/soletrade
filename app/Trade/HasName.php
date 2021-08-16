<?php

namespace App\Trade;

trait HasName
{
    public static function name(): string
    {
        return class_basename(static::class);
    }
}
