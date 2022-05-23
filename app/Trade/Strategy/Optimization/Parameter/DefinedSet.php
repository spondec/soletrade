<?php

namespace App\Trade\Strategy\Optimization\Parameter;

class DefinedSet extends ParameterSet
{
    public function __construct(public readonly array $values)
    {
    }

    public function iterator(): \Iterator
    {
        foreach ($this->values as $value)
        {
            yield $value;
        }
    }

    public function values(): array
    {
        return $this->values;
    }

    public function count(): int
    {
        return count($this->values);
    }
}