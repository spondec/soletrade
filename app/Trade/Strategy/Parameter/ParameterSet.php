<?php

namespace App\Trade\Strategy\Parameter;

abstract class ParameterSet
{
    abstract public function values(): array;

    abstract public function count(): int;

    abstract public function iterator(): \Iterator;
}
