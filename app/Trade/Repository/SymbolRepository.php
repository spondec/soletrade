<?php

declare(strict_types=1);

namespace App\Trade\Repository;

use App\Models\Symbol;
use App\Trade\CandleMap;
use App\Trade\Exchange\Exchange;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;

class SymbolRepository extends Repository
{
    protected static array $nextCandleCache = [];

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
     * @return \stdClass[]
     */
    #[ArrayShape(['lowest' => \stdClass::class, 'highest' => \stdClass::class])]
    public function assertLowestHighestCandle(int $symbolId, int $startDate, int $endDate): array
    {
        if ($startDate >= $endDate)
        {
            throw new \InvalidArgumentException('Start date can not be greater than or equal to the end date.');
        }

        /** @noinspection PhpStrictTypeCheckingInspection */
        $query = DB::table(DB::raw('candles USE INDEX(candles_symbol_id_t_unique)'))
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

    public function assertCandlesLimit(Symbol  $symbol,
                                       int     $startDate,
                                       ?int    $limit,
                                       ?string $interval = null,
                                       bool    $includeStart = false): Collection
    {
        $symbolId = $this->findSymbolIdForInterval($symbol, $interval);

        $candles = DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', $includeStart ? '>=' : '>', $startDate)
            ->orderBy('t', 'ASC');

        if ($limit)
        {
            $candles->limit($limit);
        }

        $candles = $candles->get();

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
            $interval,
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

    public function fetchCandle(Symbol $symbol, int $timestamp, ?string $interval = null): ?\stdClass
    {
        return DB::table('candles')
            ->where('symbol_id', $interval ? $this->findSymbolIdForInterval($symbol, $interval) : $symbol->id)
            ->where('t', $timestamp)
            ->first();
    }

    public function getPriceDate(int $openTime, int|null $nextOpenTime, Symbol $symbol): int
    {
        if ($nextOpenTime)
        {
            return $nextOpenTime - 1000;
        }

        if ($nextCandle = $this->fetchNextCandle($symbol->id, $openTime))
        {
            return $nextCandle->t - 1000;
        }

        return $symbol->last_update;
    }

    public function fetchNextCandle(Symbol|int $symbol, int $timestamp): ?\stdClass
    {
        $id = \is_int($symbol) ? $symbol : $symbol->id;

        if ($nextCandle = static::$nextCandleCache[$id][$timestamp] ?? null)
        {
            return $nextCandle;
        }

        $candles = DB::table('candles')
            ->where('symbol_id', $id)
            ->where('t', '>', $timestamp)
            ->orderBy('t', 'ASC')
            ->limit(100)
            ->get();

        foreach ($candles as $k => $candle)
        {
            static::$nextCandleCache[$id][$candle->t] = $candles[$k + 1] ?? null;
        }

        return $candles[0] ?? null;
    }

    public function assertNextCandle(Symbol|int $symbol, int $timestamp): \stdClass
    {
        if (!$candle = $this->fetchNextCandle($symbol, $timestamp))
        {
            throw new \InvalidArgumentException("Candle for timestamp $timestamp is not closed.");
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

    public function findSymbols(Exchange|int $exchange, string|array $symbolName, string $interval): \Illuminate\Database\Eloquent\Builder
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

    public function fetchLastCandle(Symbol $symbol): \stdClass
    {
        return DB::table('candles')
            ->where('symbol_id', $symbol->id)
            ->orderBy('t', 'DESC')
            ->first();
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

    public function fetchCandlesBetween(Symbol  $symbol,
                                        int     $startDate,
                                        int     $endDate,
                                        ?string $interval = null,
                                        bool    $includeStart = false): Collection
    {
        if ($startDate >= $endDate)
        {
            throw new \LogicException('$startDate cannot be greater than or equal to $endDate.');
        }

        $symbolId = $this->findSymbolIdForInterval($symbol, $interval);

        return DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', $includeStart ? '>=' : '>', $startDate)
            ->where('t', '<=', $endDate)
            ->orderBy('t', 'ASC')
            ->get();
    }
}