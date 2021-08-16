<?php

namespace App\Repositories;

use App\Models\Symbol;
use App\Trade\CandleMap;
use App\Trade\Exchange\AbstractExchange;
use App\Trade\Indicator\AbstractIndicator;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                $symbol->candles(before: $before, limit: $limit),
                $indicators);
        }

        return $symbol;
    }

    public function mapCandles(array $candles, int $symbolId, CandleMap $map): array
    {
        $mapped = [];
        foreach ($candles as $candle)
        {
            $mapped[] = [
                'symbol_id' => $symbolId,
                't'         => $candle[$map->t],
                'o'         => $candle[$map->o],
                'c'         => $candle[$map->c],
                'h'         => $candle[$map->h],
                'l'         => $candle[$map->l],
                'v'         => $candle[$map->v],
            ];
        }
        return $mapped;
    }

    /**
     * @param array  $symbols
     * @param int    $exchangeId
     * @param string $interval
     *
     * @return array
     */
    public function insertIgnoreSymbols(array $symbols, int $exchangeId, string $interval): void
    {
        $inserts = [];

        foreach ($symbols as $symbol)
        {
            $inserts[] = [
                'symbol'      => $symbol,
                'exchange_id' => $exchangeId,
                'interval'    => $interval
            ];
        }

        DB::table('symbols')->insertOrIgnore($inserts);
    }

    public function backtest(Symbol $symbol, AbstractStrategy $strategy)
    {

    }

    public function updateCandle(int $id, array $values): int
    {
        return DB::table('candles')
            ->where('id', $id)
            ->update($values);
    }

    /**
     * @param Symbol $symbol
     */
    public function latestCandles(Symbol $symbol, string $direction = 'DESC', int $limit = 10): Collection
    {
        return DB::table('candles')
            ->where('symbol_id', $symbol->id)
            ->orderBy('t', $direction)
            ->limit($limit)
            ->get();
    }

    /**
     * @return Symbol[]
     */
    public function fetchSymbols(array $symbols, string $interval, int $exchangeId): Collection
    {
        return Symbol::query()
            ->whereIn('symbol', $symbols)
            ->where('interval', $interval)
            ->where('exchange_id', $exchangeId)
            ->get();
    }

    public function intervals(): Collection
    {
        return DB::table('symbols')->distinct()->get('interval')->pluck('interval');
    }

//    public function updateCandle(Symbol $symbol, int $maxRunTime = 0)
//    {
//        $fetcher = $symbol->exchange()->fetcher();
//
//        return   $fetcher->fetch($interval, $maxRunTime, $quoteAsset,
//                $symbols ? fn($v) => in_array($v, $symbols) : null);
//    }

    /**
     * @param string[] $indicators
     */
    public function initIndicators(Symbol     $symbol,
                                   Collection $candles,
                                   array      $indicators): void
    {
        foreach ($indicators as $class)
        {
            $symbol->addIndicator(new $class(symbol: $symbol, candles: $candles));
        }
    }


    /**
     * @param AbstractIndicator $indicator
     */
    public function initIndicator(Symbol     $symbol,
                                  Collection $candles,
                                  string     $indicator,
                                  array      $config = [],
                                  ?\Closure  $signalCallback = null)
    {
        $symbol->addIndicator(new $indicator($symbol, $candles, $config, $signalCallback));
    }
}