<?php

namespace App\Trade;

use App\Models\Candles;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class Scanner
{
    /**
     * @return string[]
     * @var \Closure
     */
    protected $symbolFilterer;

    public function __construct(protected AbstractExchange $exchange)
    {

    }

    /** @return string[] */
    public function setSymbolFilterer(\Closure $filterer): void
    {
        $this->symbolFilterer = $filterer;
    }

    /** @return Candles[] */
    public function scan(string $interval, string $quoteAsset = null): Collection
    {
        $filterer = $this->symbolFilterer;

        if (!$symbolList = $filterer($this->exchange->symbols($quoteAsset)))
        {
            throw new \LogicException('No symbol was given.');
        }

        $map = $this->exchange->candleMap();
        $exchange = $this->exchange->name();

        $maxDates = DB::table('candles', 'sub')
            ->select('symbol', DB::raw('MAX(end_date) AS maxDate'))
            ->whereIn('symbol', $symbolList)
            ->where('exchange', $exchange)
            ->where('interval', $interval)
            ->groupBy('symbol');

        $ids = DB::table('candles', 'main')
            ->select(DB::raw('main.id'))
            ->joinSub($maxDates, 'sub', function (JoinClause $join) {
                $join->on('main.symbol', '=', 'sub.symbol');
                $join->on('main.end_date', '=', 'sub.maxDate');
            })
            ->get()
            ->pluck('id')
            ->toArray();

        /** @var Candles[] $candles */
        $candles = Candles::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('symbol');

        foreach ($symbolList as $symbol)
        {
            do
            {
                /** @var Candles $current */
                $current = $candles[$symbol] = $candles[$symbol] ?? $this->setupCandles($exchange,
                        $symbol,
                        $interval,
                        [],
                        $map);

                $current->pop();

                $latest = $this->exchange->candles($symbol,
                    $interval,
                    $current->last()[$current->map('timestamp')] ?? 0,
                    $this->exchange->getMaxCandlesPerRequest());

                $break = count($latest) <= 1;
                $data = $current->data;

                if (($gap = Candles::MAX_LENGTH - $current->length) > 0)
                {
                    /** @noinspection SlowArrayOperationsInLoopInspection */
                    $data = array_merge($data, array_slice($latest, 0, $gap));
                    $latest = array_slice($latest, $gap);
                }

                $current->data = $data;

                foreach (array_chunk($latest, Candles::MAX_LENGTH) as $latestData)
                {
                    $new = $this->setupCandles($exchange,
                        $symbol,
                        $interval,
                        $latestData,
                        $map);
                    $new->save();
                    $candles[$symbol] = $new;
                }
                $current->save();

            } while (!$break);
        }

        return $candles;
    }

    protected function setupCandles(string $exchange, string $symbol, string $interval, mixed $data, array $map): Candles
    {
        return new Candles([
            'exchange' => $exchange,
            'symbol'   => $symbol,
            'interval' => $interval,
            'data'     => $data,
            'map'      => $map
        ]);
    }
}