<?php

namespace App\Trade;

use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;

class CandleCollection extends Collection
{
    #[Pure] public function closes(): array
    {
        return array_column($this->all(), 'c');
    }

    #[Pure] public function highs(): array

    {
        return array_column($this->all(), 'h');
    }

    #[Pure] public function lows(): array
    {
        return array_column($this->all(), 'l');
    }

    #[Pure] public function timestamps(): array
    {
        return array_column($this->all(), 't');
    }
}