<?php

namespace App\Trade;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Database\Eloquent\Collection;
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

    public function __construct(protected AbstractExchange $exchange)
    {
        $this->symbolRepo = App::make(SymbolRepository::class);
        $this->symbols = $this->exchange->symbols();
        $this->map = $this->exchange->candleMap();
        $this->limit = $this->exchange->getMaxCandlesPerRequest();
    }

    protected function insertSymbols(string $interval)
    {
        $this->symbolRepo->insertIgnoreSymbols($this->symbols,
            $exchangeId = $this->exchange->id(),
            $interval);
        return $this->symbolRepo->fetchSymbols($this->symbols,
            $interval,
            $exchangeId);
    }

    /** @return Symbol[] */
    public function updateByInterval(string $interval, int $maxRunTime = 0, ?\Closure $filter = null): ?Collection
    {
        $startTime = time();

        $symbols = $this->insertSymbols($interval);
        if ($filter) $symbols = $symbols->filter($filter)->values();

        if (!$symbols->count())
        {
            throw new \LogicException('No symbol was given.');
        }

        foreach ($symbols as $key => $symbol)
        {
            $remaining = $maxRunTime - (time() - $startTime);

            if (($maxRunTime > 0 && $remaining <= 0) ||
                !$this->update($symbol, $maxRunTime > 0 ? $remaining : 0))
            {
                if (($length = $key - 1) < 1) //nothing to return if the length is non positive
                {
                    return null;
                }

                return $symbols->slice(0, $length);
            }
        }

        return $symbols;
    }

    public function update(Symbol $symbol, int $maxRunTime = 0): bool
    {
        $startTime = time();
        $id = $symbol->id;

        try
        {
            do
            {
                DB::select(DB::raw('LOCK TABLES candles WRITE, symbols WRITE'));
                $currentCandles = $this->symbolRepo->latestCandles($symbol, 'DESC', 2);
                $currentLastCandle = $currentCandles->shift();
                $start = $currentCandles->first()->t ?? 0;

                $symbol->last_update = time();
                $latestCandles = $this->exchange->candles($symbol->symbol,
                    $symbol->interval,
                    $start,
                    $this->limit);
                $inserts = $this->symbolRepo->mapCandles($latestCandles, $id, $this->map);

                $break = count($latestCandles) <= 1;

                if (isset($latestCandles[0]) && $currentLastCandle)
                {
                    if ($latestCandles[0][$this->map->t] != $currentLastCandle->t)
                    {
                        throw new \LogicException("Candle corruption detected! Symbol ID: {$id}");
                    }

                    $this->symbolRepo->updateCandle($currentLastCandle->id, $inserts[0]);
                    unset($inserts[0]);
                }

                if ($inserts)
                {
                    DB::table('candles')->insert($inserts);
                }

                $symbol->save();

                if ($maxRunTime > 0 && time() - $startTime >= $maxRunTime)
                {
                    return false;
                }

            } while (!$break);
            return true;
        } finally
        {
            DB::select(DB::raw('UNLOCK TABLES'));
        }
    }
}