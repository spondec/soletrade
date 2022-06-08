<?php

namespace App\Trade\Config;

use Illuminate\Contracts\Support\Arrayable;

abstract class Config implements Arrayable
{
    public static function fromArray(array $data): static
    {
        return new static(...$data);
    }

    public function toArray(): array
    {
        return \get_object_vars($this);
    }
}
