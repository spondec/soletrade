<?php

namespace App\Trade\Strategy;

use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Indicator\FibonacciRetracement;
use App\Trade\Indicator\MACD;
use App\Trade\Indicator\RSI;
use App\Trade\Scanner;
use App\Trade\Name;

abstract class AbstractStrategy implements Name
{
    const ALLOWED_INTERVALS = [];

    /** @var int - seconds */
    const SCAN_INTERVAL = 300;
    const INTERVALS = [];
    const CANDLE_LIMIT = 1000;

    const INDICATORS = [
        MACD::class                 => [],
        RSI::class                  => [],
        FibonacciRetracement::class => []
    ];

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