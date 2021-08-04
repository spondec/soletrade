<?php

namespace App\Trade\Strategy;

use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Scanner;
use App\Trade\NameTrait;

abstract class AbstractStrategy
{
    use NameTrait;

    /** @var int - seconds */
    const SCAN_INTERVAL = 300;
    const INTERVALS = [];
    const CANDLE_LIMIT = 1000;

    const INDICATORS = [];

    public function __construct(protected Scanner $scanner, protected SymbolRepository $symbolRepo)
    {
    }

    public function check(): ?TradeSetup
    {
        foreach (self::INTERVALS as $interval)
        {
            foreach ($symbols[$interval] = $this->scanner->scan($interval) as $symbol)
            {
                $this->symbolRepo->initIndicators($symbol,
                    $symbol->candles(limit: static::CANDLE_LIMIT),
                    static::INDICATORS);
            }
        }
    }
}