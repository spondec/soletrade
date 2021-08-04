<?php

namespace App\Trade;

trait Name
{
    public static function name(): string
    {
        return class_basename(static::class);
    }
}
