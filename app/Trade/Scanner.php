<?php

namespace App\Trade;

use App\Models\Candles;
use App\Trade\Exchange\AbstractExchange;
use App\Trade\Strategy\AbstractStrategy;

class Scanner
{
    public function __construct(protected AbstractExchange $exchange)
    {

    }

    /** @return string[] */
    protected function filterSymbols(array $symbols): array
    {
        return $symbols;
    }

    /** Returns the latest 1000 candles for a given interval. */
    public function scan(AbstractStrategy $strategy, string $interval): array
    {
        if (!$symbolList = $this->filterSymbols($this->exchange->symbolList()))
        {
            throw new \LogicException('No symbol was given.');
        }

        $map = $this->exchange->candleMap();
        $exchange = mb_strtoupper($this->exchange->name());

        /** @var Candles[] $candles */
        $candles = Candles::query()
            ->where('exchange', $exchange)
            ->where('symbol', $symbolList)
            ->where('interval', $interval)
            ->orderBy('start_date')
            ->limit(1)
            ->get(
//                ['symbol', 'start_date', 'end_date']
            )
            ->keyBy('symbol');

        foreach ($symbolList as $symbol)
        {
            $current = $candles[$symbol] ?? null;
            $latest = $this->exchange->candles(
                $symbol,
                $interval,
                $current->last()[$current->map('open')] ?? 0,
                time());

            $data = $current->data;
            array_pop($data);

            if (($gap = Candles::MAX_CANDLE_LENGTH - $current->length) > 0)
            {
                $current->data = array_merge($data, array_slice($latest, 0, $gap));
                $latest = array_slice($latest, $gap);
            }

            foreach (array_chunk($latest, Candles::MAX_CANDLE_LENGTH) as $data)
            {
                $new = new Candles([
                    'exchange' => $exchange,
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'data' => $data,
                    'map' => $map
                ]);

                if ($new->save())
                {
                    $candles[$symbol] = $new;
                }
            }

            $strategy->check($candles[$symbol]);
        }

        return $candles;
    }
}