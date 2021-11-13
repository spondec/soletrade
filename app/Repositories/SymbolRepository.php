<?php

namespace App\Repositories;

use App\Models\Symbol;
use App\Trade\CandleMap;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;

class SymbolRepository
{
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

    public function fetchLowerIntervalCandles(\stdClass $candle, Symbol $symbol, string $interval)
    {
        $nextCandle = $this->fetchNextCandle($candle->symbol_id, $candle->t);

        return $this->fetchCandlesBetween($symbol, $candle->t, $nextCandle->t, $interval, true);
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
     * @param \stdClass[] $candles
     *
     * @return \stdClass[]
     */
    #[ArrayShape(['lowest' => "\stdClass", 'highest' => "\stdClass"])]
    public function fetchLowestHighestCandle(int $symbolId, int $startDate, int $endDate): array
    {
        if ($startDate >= $endDate)
        {
            throw new \InvalidArgumentException('Start date can not be greater than or equal to end date.');
        }

        $query = DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', '>=', $startDate)
            ->where('t', '<=', $endDate);

        $highest = $query->orderBy('l', 'ASC')->first();
        $lowest = $query->reorder('h', 'DESC')->first();

        if (empty($highest) || empty($lowest))
        {
            throw new \LogicException('Lowest and/or highest candles was not found.');
        }

        return [
            'highest' => $highest,
            'lowest'  => $lowest
        ];
    }

    public function fetchCandlesLimit(Symbol $symbol,
                                      int $startDate,
                                      int $limit,
                                      ?string $interval = null): Collection
    {
        $symbolId = $this->findSymbolIdForInterval($symbol, $interval);

        $candles = DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', '>', $startDate)
            ->orderBy('t', 'ASC')
            ->limit($limit)
            ->get();

        if (!$candles->first())
        {
            throw new \UnexpectedValueException("$symbol->symbol-$interval candles was not found.");
        }

        return $candles;
    }

    public function fetchCandlesBetween(Symbol $symbol,
                                        int $startDate,
                                        int $endDate,
                                        ?string $interval = null,
                                        bool $includeStart = false): Collection
    {
        if ($startDate >= $endDate)
        {
            throw new \LogicException('$startDate cannot be greater than or equal to $endDate.');
        }

        $symbolId = $this->findSymbolIdForInterval($symbol, $interval);

        $candles = DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', $includeStart ? '>=' : '>', $startDate)
            ->where('t', '<=', $endDate)
            ->orderBy('t', 'ASC')
            ->get();

        if (!$candles->first())
        {
            throw new \UnexpectedValueException("$symbol->symbol-$interval candles was not found.");
        }

        return $candles;
    }

    public function findSymbolIdForInterval(Symbol $symbol, ?string $interval = null): int
    {
        return !$interval || $symbol->interval === $interval ? $symbol->id :
            DB::table('symbols')
                ->where('exchange_id', $symbol->exchange_id)
                ->where('interval', $interval)
                ->where('symbol', $symbol->symbol)
                ->get('id')->first()->id;
    }

    public function fetchCandle(Symbol $symbol, int $timestamp, string $interval): \stdClass
    {
        return DB::table('candles')
            ->where('symbol_id', $this->findSymbolIdForInterval($symbol, $interval))
            ->where('t', $timestamp)
            ->first();
    }

    public function fetchNextCandle(int $symbolId, int $timestamp): \stdClass
    {
        $candle = DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', '>', $timestamp)
            ->orderBy('t', 'ASC')
            ->first();

        if (!$candle)
        {
            throw new \InvalidArgumentException("Candle for timestamp $timestamp is not closed to get the next candle.");
        }

        return $candle;
    }

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

    public function updateCandle(int $id, array $values): int
    {
        return DB::table('candles')
            ->where('id', $id)
            ->update($values);
    }

    /**
     * @param Symbol $symbol
     */
    public function fetchLatestCandles(Symbol $symbol, string $direction = 'DESC', int $limit = 10): Collection
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

    public function fetchIntervals(): Collection
    {
        return DB::table('symbols')
            ->selectRaw(DB::raw('DISTINCT(BINARY `interval`) as `interval`'))
            ->get()->pluck('interval');
    }

    public function fetchLastCandle(Symbol $symbol): \stdClass
    {
        return DB::table('candles')
            ->where('symbol_id', $symbol->id)
            ->orderBy('t', 'DESC')
            ->first();
    }

    public function fetchSymbol(AbstractExchange $exchange, string $symbolName, string $interval): ?Symbol
    {
        /** @var Symbol $symbol */
        $symbol = Symbol::query()
            ->where('exchange_id', $exchange::instance()->id())
            ->where('symbol', $symbolName)
            ->where('interval', $interval)
            ->first();
        return $symbol;
    }
}