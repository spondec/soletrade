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
        $symbolList = $this->exchange->symbolList();
        $map = $this->exchange->candleMap();
        $exchange = mb_strtoupper($this->exchange->name());
        $latestCandles = [];

        foreach (
            Candles::query()
                ->where('exchange', $exchange)
                ->where('symbol', $symbolList)
                ->where('interval', $interval)
                ->orderBy('start_date')
                ->limit(1)
                ->get(['symbol', 'start_date', 'end_date']) as $item)
        {
            $latestCandles[$item->symbol] = $item;
        }

        foreach ($symbolList as $symbol)
        {
            $newCandles = $this->exchange->candles($symbol, $interval,
                $latestCandles[$symbol]['end_date'] ?? 0, time());

            foreach (array_chunk($newCandles, Candles::MAX_DATA_LENGTH) as $data)
            {
                $new = new Candles(
                    [
                        'exchange' => $exchange,
                        'symbol' => $symbol,
                        'interval' => $interval,
                        'data' => $data,
                        'map' => $map,
                        'start_date' => reset($data)[$map['time']],
                        'end_date' => end($data)[$map['time']],
                    ]);

                if ($new->save())
                {
                    $latestCandles[$symbol][] = $new;
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