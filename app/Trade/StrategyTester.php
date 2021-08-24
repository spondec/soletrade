<?php

namespace App\Trade;

use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StrategyTester
{
    use HasConfig;

    protected array $config = [
        'maxCandles' => null,
        'startDate'  => null,
        'endDate'    => null
    ];

    protected array $result = [];

    public function __construct(protected SymbolRepository $symbolRepo, array $config = [])
    {
        $this->mergeConfig($config);
    }

    public function run(string $strategyClass, Symbol $symbol): array
    {
        $strategy = $this->setupStrategy($strategyClass, $this->config);

        Log::execTime(static function () use (&$symbol) {

            if ($symbol->last_update < time() - 3600)
                $symbol->exchange()->updater()->update($symbol);
        }, 'CandleUpdater::update()');

        Log::execTime(static function () use (&$symbol, &$strategy, &$result) {
            $result = $strategy->run($symbol);
        }, 'StrategyTester::run()');

        Log::execTime(function () use (&$symbol, &$result) {
            $this->prepareResult($result, $symbol);
        }, 'StrategyTester::prepareResult()');

        return $this->result;
    }

    protected function setupStrategy(string $class, array $config): AbstractStrategy
    {
        if (!is_subclass_of($class, AbstractStrategy::class))
        {
            throw new \InvalidArgumentException('Invalid strategy class: ' . $class);
        }

        return new $class(config: $config);
    }

    protected function prepareResult(array $result, Symbol $symbol): void
    {
        /**
         * @var TradeSetup[] $trades
         */
        foreach ($result as $id => $trades)
        {
            $this->result['trade_setups'][$id] = $this->pairEvaluateSummarize($trades);
        }

        foreach ($symbol->cachedSignals() as $indicator => $signals)
        {
            $this->result['signals'][$indicator] = $this->pairEvaluateSummarize($signals);
        }
    }

    /**
     * @param TradeSetup[]|Signal[] $trades
     */
    protected function pairEvaluateSummarize(array|Collection $trades): array
    {
        $paired = [];
        foreach ($trades as $trade)
        {
            if (!isset($entry))
            {
                $entry = $trade;
                continue;
            }

            if ($entry->side !== $trade->side)
            {
                $paired[] = [
                    'result' => $this->evaluateTrade($entry, $trade),
                    'entry'  => $entry,
                    'exit'   => $trade
                ];

                $entry = $trade;
            }
        }

        return ['trades' => $paired, 'summary' => $this->summarize($paired)];
    }

    protected function evaluateTrade(TradeSetup|Signal $entry, TradeSetup|Signal $exit): array
    {
        $this->assertEntryExitTime($entry, $exit);

        $side = $entry->side;
        $entryPrice = $entry->price;
        $buy = $side === Signal::BUY;

        $candle = $this->getLowestHighestPriceBetween($entry, $exit);

        $result['highest_price'] = $highest = (float)$candle->h;
        $result['lowest_price'] = $lowest = (float)$candle->l;

        $stop = ($stopPrice = $entry->stop_price) && (
                ($buy && $lowest <= $stopPrice) ||
                (!$buy && $highest >= $stopPrice)
            );
        $result['stop'] = $stop;
        $result['stop_price'] = $stopPrice;

        if ($validPrice = ($entryPrice >= $lowest && $entryPrice <= $highest))
        {
            $entry->valid_price = true;
            $entry->save();

            $result = array_merge($result, $this->getLowestHighestUntilEntryPrice($entry, $exit));
        }

        $roi = [
            'realized_roi' => $validPrice ? $this->calcRoi($side, $entryPrice, $stop ? $stopPrice : $exit->price) : 0,
            'highest_roi'  => $validPrice ? $this->calcRoi($side, $entryPrice, $buy ? $highest : $lowest) : 0,
            'lowest_roi'   => $validPrice ? $this->calcRoi($side, $entryPrice, !$buy ? $highest : $lowest) : 0,
        ];

        //TODO:: handle take profits
        return array_merge($result, $roi);
    }

    protected function assertEntryExitTime(Signal|TradeSetup $entry, Signal|TradeSetup $exit): void
    {
        if ($exit->timestamp <= $entry->timestamp)
        {
            throw new \LogicException('Exit trade must be older than entry trade.');
        }
    }

    protected function getLowestHighestPriceBetween(Signal|TradeSetup $entry, Signal|TradeSetup $exit): object
    {
        $candle = DB::table('candles')
            ->select(DB::raw('max(h) as h, min(l) as l'))
            ->where('symbol_id', $entry->symbol_id)
            ->where('t', '>=', $entry->timestamp)
            ->where('t', '<=', $exit->timestamp)
            ->first();

        if (!$candle)
        {
            throw new \UnexpectedValueException('Highest/lowest price between the two trades was not found.');
        }

        return $candle;
    }

    protected function getLowestHighestUntilEntryPrice(Signal|TradeSetup $entry, Signal|TradeSetup $exit): array
    {
        $candles = DB::table('candles')
            ->where('symbol_id', $entry->symbol_id)
            ->where('t', '>=', $entry->timestamp)
            ->where('t', '<=', $exit->timestamp)
            ->orderBy('t', 'ASC')
            ->get();

        if (!$candles)
        {
            throw new \UnexpectedValueException($entry->symbol()->first()->name . ' candles are missing!');
        }

        $lowestUntilEntry = INF;
        $highestUntilEntry = 0;
        $entryPrice = $entry->price;
        $realEntryTime = null;

        foreach ($candles as $candle)
        {
            $low = $candle->l;
            $high = $candle->h;

            if ($low < $lowestUntilEntry)
            {
                $lowestUntilEntry = $low;
            }

            if ($high > $highestUntilEntry)
            {
                $highestUntilEntry = $high;
            }

            if ($entryPrice >= $low && $entryPrice <= $high)
            {
                $realEntryTime = (float)$candle->t;
                break;
            }
        }

        return [
            'highest_until_entry' => (float)$highestUntilEntry,
            'lowest_until_entry'  => (float)$lowestUntilEntry,
            'real_entry_time'     => $realEntryTime
        ];
    }

    protected function calcRoi(string $side, int|float $entryPrice, int|float $exitPrice): int|float
    {
        $roi = ($exitPrice - $entryPrice) * 100 / $entryPrice;

        if ($side === Signal::SELL) $roi *= -1;

        return round($roi, 2);
    }

    protected function summarize(array $paired): array
    {
        if (!$paired)
        {
            return [];
        }

        $balance = 100;

        foreach ($paired as $pair)
        {
            $pnl = $this->calculatePnl($balance, $roi[] = (float)$pair['result']['realized_roi']);
            $balance += $pnl;
        }

        return [
            'roi'     => $roi = round($balance - 100, 2),
            'avg_roi' => round($roi / count($paired), 2)
        ];
    }

    protected function calculatePnl(float $balance, float $roi): float|int
    {
        return $balance * $roi / 100;
    }
}