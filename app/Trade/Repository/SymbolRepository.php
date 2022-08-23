<?php

declare(strict_types=1);

namespace App\Trade\Repository;

use App\Models\Symbol;
use App\Trade\CandleMap;
use App\Trade\Exchange\Exchange;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;

class SymbolRepository extends Repository
{
    /**
     * @param string[]|array[] $indicators
     */
    public function initIndicators(Symbol     $symbol,
                                   Collection $candles,
                                   array      $indicators): void
    {
        foreach ($indicators as $class => $config)
        {
            if (!\is_array($config))
            {
                $class = $config;
                $config = [];
            }
            $symbol->addIndicator(new $class(symbol: $symbol, candles: $candles, config: $config));
        }
    }

    public function mapCandles(array $candles, Symbol $symbol, CandleMap $map): array
    {
        $mapped = [];
        $iterator = new \ArrayIterator($candles);
        $symbolId = $symbol->id;
        while ($iterator->current())
        {
            $candle = $iterator->current();
            $iterator->next();
            $next = $iterator->current();

            $mapped[] = [
                'symbol_id'  => $symbolId,
                't'          => $candle[$map->t],
                'o'          => $candle[$map->o],
                'c'          => $candle[$map->c],
                'h'          => $candle[$map->h],
                'l'          => $candle[$map->l],
                'v'          => $candle[$map->v],
                'price_date' => $next ? $next[$map->t] - 1000 : $symbol->last_update
            ];
        }
        return $mapped;
    }

    /**
     * @return object[]
     */
    #[ArrayShape(['lowest' => 'object', 'highest' => 'object'])]
    public function assertLowestHighestCandle(int $symbolId, int $startDate, int $endDate): array
    {
        if ($startDate >= $endDate)
        {
            throw new \InvalidArgumentException('Start date can not be greater than or equal to the end date.');
        }

        /** @noinspection PhpStrictTypeCheckingInspection */
        $query = DB::table(DB::raw('candles USE INDEX(candles_t_symbol_id_unique)'))
            ->where('symbol_id', $symbolId)
            ->where('t', '>=', $startDate)
            ->where('t', '<=', $endDate);

        $lowest = $query->orderBy('l', 'ASC')->first();
        $highest = $query->reorder('h', 'DESC')->first();

        if (empty($highest) || empty($lowest))
        {
            throw new \LogicException('Lowest and/or highest candles was not found.');
        }

        return [
            'highest' => $highest,
            'lowest'  => $lowest
        ];
    }

    public function findSymbolIdForInterval(Symbol $symbol, ?string $interval = null): int
    {
        return !$interval || $symbol->interval === $interval ? $symbol->id :
            DB::table('symbols')
                ->where('exchange_id', $symbol->exchange_id)
                ->whereRaw(DB::raw('BINARY `interval` = ?'), $interval)
                ->where('symbol', $symbol->symbol)
                ->get('id')->first()->id;
    }

    public function assertCandlesBetween(Symbol  $symbol,
                                         int     $startDate,
                                         int     $endDate,
                                         ?string $interval = null,
                                         bool    $includeStart = false): Collection
    {
        $candles = $this->fetchCandlesBetween($symbol,
            $startDate,
            $endDate,
            $includeStart);

        if (!$candles->first())
        {
            throw new \UnexpectedValueException("$symbol->symbol-$interval candles was not found.");
        }

        return $candles;
    }

    public function findCandles(Symbol $symbol): Builder
    {
        return DB::table('candles')->where('symbol_id', $symbol->id);
    }

    public function fetchCandle(Symbol $symbol, int $timestamp, ?string $interval = null): ?object
    {
        return DB::table('candles')
            ->where('symbol_id', $interval ? $this->findSymbolIdForInterval($symbol, $interval) : $symbol->id)
            ->where('t', $timestamp)
            ->first();
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
        return $this->findSymbols($exchangeId, $symbols, $interval)->get();
    }

    public function findSymbols(Exchange|int $exchange, string|array $symbolName, string $interval): EloquentBuilder
    {
        $query = Symbol::query()
            ->where('exchange_id', \is_int($exchange) ? $exchange : $exchange::instance()->model()->id)
            ->whereRaw(DB::raw('BINARY `interval` = ?'), $interval);

        if (\is_array($symbolName))
        {
            $query->whereIn('symbol', $symbolName);
        }
        else
        {
            $query->where('symbol', $symbolName);
        }

        return $query;
    }

    public function fetchIntervals(): Collection
    {
        return DB::table('symbols')
            ->selectRaw(DB::raw('DISTINCT(BINARY `interval`) as `interval`'))
            ->get()->pluck('interval');
    }

    public function fetchSymbolFromExchange(Exchange $exchange, string $symbolName, string $interval)
    {
        $filter = static fn(Symbol $symbol): bool => $symbol->symbol === $symbolName && $symbol->interval === $interval;
        return $exchange::instance()
            ->update()
            ->byInterval(interval: $interval, filter: $filter)
            ?->first();
    }

    public function fetchSymbol(Exchange $exchange, string $symbolName, string $interval): ?Symbol
    {
        /** @var Symbol $symbol */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->findSymbols($exchange, $symbolName, $interval)->first();
    }

    public function fetchCandlesBetween(Symbol $symbol,
                                        int    $startDate,
                                        int    $endDate,
                                        bool   $includeStart = false): Collection
    {
        if ($startDate >= $endDate)
        {
            throw new \LogicException('$startDate cannot be greater than or equal to $endDate.');
        }

        return DB::table('candles')
            ->where('symbol_id', $symbol->id)
            ->where('t', $includeStart ? '>=' : '>', $startDate)
            ->where('t', '<=', $endDate)
            ->orderBy('t', 'ASC')
            ->get();
    }
}