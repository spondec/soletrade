<?php

namespace App\Trade\Indicator;

use App\Models\Symbol;
use App\Models\Signal;
use App\Trade\VersionableInterface;

abstract class AbstractIndicator implements VersionableInterface
{
    protected array $config = [];
    protected array $data;

    public function __construct(protected Symbol $symbol, array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->data = $this->calculate();
    }

    abstract protected function calculate(): array;

    abstract public function signal(): ?Signal;

    public function getSymbol(): Symbol
    {
        return $this->symbol;
    }

    public function data(): array
    {
        return $this->data;
    }
}