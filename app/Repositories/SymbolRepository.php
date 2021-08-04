<?php

namespace App\Repositories;

use App\Models\Symbol;
use App\Trade\Exchange\AbstractExchange;
use App\Trade\Indicator\AbstractIndicator;
use Illuminate\Support\Collection;

class SymbolRepository
{
    public function candles(AbstractExchange $exchange,
                            string           $symbol,
                            string           $interval,
                            int              $before = null,
                            int              $limit = null,
                            array            $indicators = []): ?Symbol
    {
        /** @var Symbol $symbol */
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $symbol = Symbol::query()
            ->where('exchange_id', $exchange::instance()->id())
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->first();

        if ($symbol)
        {
            $this->initIndicators($symbol,
                $symbol->candles($before, $limit),
                $indicators);
        }

        return $symbol;
    }

    public function initIndicators(Symbol $symbol, Collection $candles, array $classes): void
    {
        /** @var  AbstractIndicator $class */
        foreach ($classes as $class)
        {
            $symbol->addIndicator(new $class($symbol, $candles));
        }
    }
}