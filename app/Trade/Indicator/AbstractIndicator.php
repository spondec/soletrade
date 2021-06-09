<?php

namespace App\Trade\Indicator;

use App\Models\Candles;

abstract class AbstractIndicator
{
    protected array $config = [];
    protected array $data;

    abstract protected function calculate(): array;

    public function getCandles():Candles
    {
        return $this->candles;
    }

    public function __construct(protected Candles $candles, array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->data = $this->calculate();
    }

    public function name(): string
    {
        return class_basename(self::class);
    }

    public function data()
    {
        return $this->data;
    }
}