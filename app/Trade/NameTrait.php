<?php

namespace App\Trade;

trait NameTrait
{
    public static function name(): string
    {
        return class_basename(static::class);
    }
}
