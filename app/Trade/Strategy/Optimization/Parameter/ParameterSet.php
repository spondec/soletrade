<?php

namespace App\Trade\Strategy\Optimization\Parameter;

abstract class ParameterSet
{
    abstract public function values(): array;

    abstract public function count(): int;

    abstract public function iterator(): \Iterator;
}