<?php

namespace App\Trade;

use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;

class CandleCollection extends Collection
{
    #[Pure] protected function closes(): array
    {
        return array_column($this->all(), 'c');
    }

    #[Pure] protected function highs(): array

    {
        return array_column($this->all(), 'h');
    }

    #[Pure] protected function lows(): array
    {
        return array_column($this->all(), 'l');
    }

    #[Pure] protected function timestamps(): array
    {
        return array_column($this->all(), 't');
    }
}