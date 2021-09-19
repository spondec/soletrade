<?php

namespace App\Repositories;

use App\Models\Symbol;
use App\Trade\CandleMap;
use App\Trade\Exchange\AbstractExchange;
use App\Trade\Indicator\AbstractIndicator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;

class SymbolRepository
{
    public function candles(AbstractExchange $exchange,
                            string           $symbol,
                            string           $interval,
                            int              $limit = null,
                            array            $indicators = [],
                            int              $end = null,
                            int              $start = null): ?Symbol
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
                $symbol->candles(limit: $limit, start: $start, end: $end),
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

    public function getNextCandleOpen(int $symbolId, int $timestamp): int
    {
        return DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', '>', $timestamp)
            ->orderBy('t', 'ASC')
            ->first()?->t;
    }

    /**
     * @param \stdClass[] $candles
     *
     * @return \stdClass[]
     */
    #[ArrayShape(['lowest' => "\stdClass", 'highest' => "\stdClass"])]
    public function getLowestHighest(Collection $candles): array
    {
        $highest = 0;
        $lowest = INF;
        $result = [];

        foreach ($candles as $candle)
        {
            if ($candle->l < $lowest)
            {
                $lowest = $candle->l;
                $result['lowest'] = $candle;
            }

            if ($candle->h > $highest)
            {
                $highest = $candle->h;
                $result['highest'] = $candle;
            }
        }

        if (empty($result['highest']) || empty($result['lowest']))
        {
            throw new \LogicException('Lowest and/or highest candles was not found.');
        }

        return $result;
    }

    public function findSymbolIdForInterval(Symbol $symbol, ?string $interval = null): ?int
    {
        return !$interval || $symbol->interval === $interval ? $symbol->id :
            DB::table('symbols')
                ->where('exchange_id', $symbol->exchange_id)
                ->where('interval', $interval ?? $symbol->interval)
                ->where('symbol', $symbol->symbol)
                ->get('id')?->first()->id;
    }

    public function fetchCandlesBetween(Symbol $symbol, int $startDate, int $endDate, ?string $interval = null): Collection
    {
        if ($startDate >= $endDate)
        {
            throw new \LogicException('$startDate cannot be greater than or equal to $endDate.');
        }

        $interval = $interval ?? $symbol->interval;
        $symbolId = $this->findSymbolIdForInterval($symbol, $interval);

        $builder = $this->buildBetween($symbolId, $startDate, $endDate);
        $candles = $builder->orderBy('t', 'ASC')->get();

        if (!$candles->count())
        {
            throw new \UnexpectedValueException("$symbol->symbol-$interval candles was not found.");
        }

        return $candles;
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
        return DB::table('symbols')
            ->selectRaw(DB::raw('DISTINCT(BINARY `interval`) as `interval`'))
            ->get()->pluck('interval');
    }

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
                                  ?\Closure  $signalCallback = null): void
    {
        $symbol->addIndicator(new $indicator($symbol, $candles, $config, $signalCallback));
    }

    protected function buildBetween(int $symbolId, int $startDate, int $endDate): \Illuminate\Database\Query\Builder
    {
        $nextOpenStart = $this->getNextCandleOpen($symbolId, $startDate);
        $nextOpenEnd = $this->getNextCandleOpen($symbolId, $endDate);

        return DB::table('candles')
            ->where('symbol_id', $symbolId)
            ->where('t', '>', $nextOpenStart)
            ->where('t', '<=', $nextOpenEnd);
    }
}