<?php

namespace App\Trade;

use App\Models\Symbol;
use App\Trade\Exchange\Exchange;
use App\Trade\Repository\SymbolRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class CandleUpdater
{
    protected SymbolRepository $symbolRepo;

    protected int $limit;
    protected CandleMap $map;
    /**
     * @var string[]
     */
    protected array $symbols;

    public static bool $isShutdownCallbackRegistered = false;

    public function __construct(protected Exchange $exchange)
    {
        $this->symbolRepo = App::make(SymbolRepository::class);

        $fetch = $this->exchange->fetch();

        $this->symbols = $fetch->symbols();
        $this->map = $fetch->candleMap();
        $this->limit = $fetch->getMaxCandlesPerRequest();

        if (!static::$isShutdownCallbackRegistered) {
            on_shutdown(static function () {
                // shutdowns may interrupt unlock query
                DB::unprepared('UNLOCK TABLES');
            });
            static::$isShutdownCallbackRegistered = true;
        }
    }

    /**
     * @return Collection<Symbol>|null
     */
    public function byInterval(string $interval, int $maxRunTime = 0, ?\Closure $filter = null): ?Collection
    {
        $startTime = \time();

        $symbols = $this->indexSymbols($interval);

        if ($filter) {
            $symbols = $symbols->filter($filter)->values();
        }

        if (!$symbols->first()) {
            throw new \LogicException('No symbol was given.');
        }

        foreach ($symbols as $key => $symbol) {
            $remaining = $maxRunTime - (\time() - $startTime);

            if (($maxRunTime > 0 && $remaining <= 0) ||
                !$this->bySymbol($symbol, $maxRunTime > 0 ? $remaining : 0)) {
                if (($length = $key - 1) < 1) { // nothing to return if the length is non-positive
                    return null;
                }

                return $symbols->slice(0, $length);
            }
        }

        return $symbols;
    }

    public function bySymbol(Symbol $symbol, int $maxRunTime = 0): bool
    {
        Log::execTimeStart($task = "Updating {$this->exchange::name()} $symbol->symbol-$symbol->interval candles");
        $startTime = \time();
        $id = $symbol->id;

        try {
            do {
                DB::unprepared('LOCK TABLES candles WRITE, symbols WRITE');
                $currentCandles = $this->symbolRepo->fetchLatestCandles($symbol, 'DESC', 2);
                $currentLastCandle = $currentCandles->shift();
                $start = $currentCandles->first()->t ?? 0;

                $symbol->last_update = \time() * 1000;
                $latestCandles = $this->exchange->fetch()->candles($symbol->symbol,
                    $symbol->interval,
                    $start,
                    $this->limit);
                $inserts = $this->symbolRepo->mapCandles($latestCandles, $id, $this->map);

                $break = \count($latestCandles) <= 1;

                if (isset($latestCandles[0]) && $currentLastCandle) {
                    if ($latestCandles[0][$this->map->t] != $currentLastCandle->t) {
                        throw new \LogicException("Candle corruption detected! Symbol ID: $id");
                    }

                    $this->symbolRepo->updateCandle($currentLastCandle->id, $inserts[0]);
                    unset($inserts[0]);
                }

                if ($inserts) {
                    DB::table('candles')->insert($inserts);
                }

                $symbol->save();

                if ($maxRunTime > 0 && \time() - $startTime >= $maxRunTime) {
                    return false;
                }
            } while (!$break);

            return true;
        } finally {
            DB::unprepared('UNLOCK TABLES');
            Log::execTimeFinish($task);
        }
    }

    public function bulkIndexSymbols(array $intervals): Collection
    {
        $symbols = [];
        foreach ($intervals as $interval) {
            $symbols[$interval] = $this->indexSymbols($interval);
        }

        return new Collection($symbols);
    }

    public function indexSymbols(string $interval): Collection
    {
        $this->symbolRepo->insertIgnoreSymbols($this->symbols,
            $id = $this->exchange->model()->id,
            $interval);

        return $this->symbolRepo->fetchSymbols($this->symbols,
            $interval,
            $id);
    }
}
