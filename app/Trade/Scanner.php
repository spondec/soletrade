<?php

namespace App\Trade;

use App\Models\Symbol;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class Scanner
{
    /**
     * @return string[]
     */
    protected ?\Closure $symbolFilterer = null;

    public function __construct(protected AbstractExchange $exchange)
    {

    }

    /** @return string[] */
    public function setFilterer(\Closure $filterer): void
    {
        $this->symbolFilterer = $filterer;
    }

    protected function filterSymbols(array &$symbols): void
    {
        if (($filterer = $this->symbolFilterer) instanceof \Closure)
        {
            $symbols = $filterer($symbols);
        }
    }

    /** @return Symbol[] */
    public function scan(string $interval, string $quoteAsset = null): Collection
    {
        $symbolList = $this->exchange->symbols($quoteAsset);
        $this->filterSymbols($symbolList);

        if (!$symbolList)
        {
            throw new \LogicException('No symbol was given.');
        }

        $map = $this->exchange->candleMap();
        $exchangeId = $this->exchange->id();

        $inserts = [];
        foreach ($symbolList as $symbol)
        {
            $inserts[] = [
                'symbol'   => $symbol,
                'exchange_id' => $exchangeId,
                'interval' => $interval
            ];
        }

        DB::table('symbols')->insertOrIgnore($inserts);

        /** @var Symbol[] $symbols */
        $symbols = Symbol::query()
            ->whereIn('symbol', $symbolList)
            ->where('interval', $interval)
            ->where('exchange_id', $exchangeId)
            ->get();

        $limit = $this->exchange->getMaxCandlesPerRequest();

        foreach ($symbols as $symbol)
        {
            $id = $symbol->id;
            do
            {
                $current = $this->fetchLastCandles($symbol, 2);

                $lastCandle = $current->first();
                $current->shift();

                $start = $current->first()->t ?? 0;
                $latest = $this->exchange->candles($symbol->symbol, $interval, $start, $limit);

                $break = count($latest) <= 1;

                $inserts = [];
                foreach ($latest as $candle)
                {
                    $inserts[] = [
                        'symbol_id' => $id,
                        't'         => $candle[$map->t],
                        'o'         => $candle[$map->o],
                        'c'         => $candle[$map->c],
                        'h'         => $candle[$map->h],
                        'l'         => $candle[$map->l],
                        'v'         => $candle[$map->v],
                    ];
                }

                if (isset($latest[0]) && $lastCandle)
                {
                    if ($latest[0][$map->t] != $lastCandle->t)
                    {
                        throw new \LogicException("$symbol candles are corrupt!");
                    }

                    DB::table('candles')
                        ->where('id', $lastCandle->id)
                        ->update($inserts[0]);
                    unset($inserts[0]);
                }

                if ($inserts)
                    DB::table('candles')->insert($inserts);

            } while (!$break);
        }

        return $symbols;
    }

    protected function setupCandles(string $exchange, string $symbol, string $interval, mixed $data, array $map): Symbol
    {
        return new Symbol([
            'exchange' => $exchange,
            'symbol'   => $symbol,
            'interval' => $interval,
            'data'     => $data,
            'map'      => $map
        ]);
    }

    /**
     * @param Symbol $symbol
     *
     * @return mixed
     */
    protected function fetchLastCandles(Symbol $symbol, int $limit = 10): \Illuminate\Support\Collection
    {
        return DB::table('candles')
            ->where('symbol_id', $symbol->id)
            ->orderBy('t', 'DESC')
            ->limit($limit)
            ->get();
    }
}