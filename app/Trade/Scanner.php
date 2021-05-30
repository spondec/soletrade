<?php

namespace App\Trade;

use App\Models\Candles;
use App\Trade\Exchange\IExchange;
use App\Trade\Strategy\AbstractStrategy;

class Scanner
{
    /** @var int - seconds */
    const SCAN_INTERVAL = 300;

    protected int $lastRunDate;

    public function scan(string $interval)
    {
        $symbolList = $this->exchange->getSymbolList();
        $map = $this->exchange->getCandleMap();
        $exchange = mb_strtoupper($this->exchange->getExchangeName());
        $collection = [];

        foreach (
            Candles::query()
                   ->where('exchange', $exchange)
                   ->where('symbol', $symbolList)
                   ->where('interval', $interval)
                   ->orderBy('start_date')
                   ->get(['symbol', 'start_date', 'end_date']) as $item)
        {
            $collection[$item->symbol][] = $item;
        }

        foreach ($symbolList as $symbol)
        {
            $candles = $collection[$symbol] ?? null;
            $start = $candles ? end($candles)['end_date'] : 0;
            $newCandles = $this->exchange->getCandlesForSymbol($symbol, $interval, $start, time());

            foreach (array_chunk($newCandles, Candles::MAX_CANDLES_PER_MODEL) as $data)
            {
                $new = new Candles(
                    [
                        'exchange'   => $exchange,
                        'symbol'     => $symbol,
                        'interval'   => $interval,
                        'data'       => $data,
                        'map'        => $map,
                        'start_date' => reset($data)[$map['time']],
                        'end_date'   => end($data)[$map['time']],
                    ]);

                if ($new->save())
                {
                    $collection[$symbol][] = $new;
                }
            }
        }
    }

    /**
     * Scanner constructor.
     *
     * @param IExchange          $exchange
     * @param AbstractStrategy[] $strategies
     */
    public function __construct(protected IExchange $exchange,
                                protected array $strategies)
    {
        foreach ($this->strategies as $strategy)
        {
            if (!$strategy instanceof AbstractStrategy)
            {
                throw new \InvalidArgumentException(
                    'Strategy must be an instance of AbstractStrategy.');
            }
        }
    }

    public function updateCandles()
    {

    }

}