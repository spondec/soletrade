<?php

namespace App\Trade\Exchange;

final class Candle
{
    public final function __construct(protected float $open,
                                      protected float $close,
                                      protected float $high,
                                      protected float $low,
                                      protected float $volume,
                                      protected int   $timestamp)
    {
    }

    public final function timestamp()
    {
        return $this->timestamp;
    }

    public final function open(): float
    {
        return $this->open;
    }

    public final function close(): float
    {
        return $this->close;
    }

    public final function high(): float
    {
        return $this->high;
    }

    public final function low(): float
    {
        return $this->low;
    }

    public final function volume(): float
    {
        return $this->volume;
    }
}