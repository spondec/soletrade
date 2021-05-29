<?php

namespace App\Trade\Indicator;

abstract class AbstractIndicator
{
    protected array $config;
    protected array $data;

    abstract public function calculate(array $closes): array;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function data()
    {
        return $this->data;
    }
}