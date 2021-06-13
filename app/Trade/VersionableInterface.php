<?php

namespace App\Trade;

interface VersionableInterface
{
    public function name(): string;

    public function version(): int;
}
