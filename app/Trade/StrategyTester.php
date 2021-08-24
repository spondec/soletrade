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

    protected function setupStrategy(string $class, array $config): AbstractStrategy
    {
        if (!is_subclass_of($class, AbstractStrategy::class))
        {
            throw new \InvalidArgumentException('Invalid strategy class provided.');
        }

        return new $class(config: $config);
    }

    public function run(string $strategyClass, Symbol $symbol): array
    {
        $strategy = $this->setupStrategy($strategyClass, $this->config);

        Log::execTime(static function () use (&$symbol) {
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

    protected function calculatePnl(float $balance, float $roi): float|int
    {
        return $balance * $roi / 100;
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
        if (($exitTime = $exit->timestamp) <= ($entryTime = $entry->timestamp))
        {
            throw new \LogicException('Exit trade must be older than entry trade.');
        }

        $side = $entry->side;
        $entryPrice = $entry->price;

        $buy = $side === Signal::BUY;

        $candle = DB::table('candles')
            ->select(DB::raw('max(h) as h, min(l) as l'))
            ->where('symbol_id', $entry->symbol_id)
            ->where('t', '>=', $entryTime)
            ->where('t', '<=', $exitTime)
            ->first();

        if (!$candle)
        {
            throw new \UnexpectedValueException('Highest/lowest price between two trades was not found.');
        }

        $highest = (float)$candle->h;
        $lowest = (float)$candle->l;

        if ($validPrice = ($entryPrice >= $lowest && $entryPrice <= $highest))
        {
            $entry->valid_price = true;
            $entry->save();
        }

        //TODO:: handle take profits
        return [
            'realized_roi'  => $validPrice ? $this->calculateRoi($side, $entryPrice, $exit->price) : 0,
            'highest_roi'   => $validPrice ? $this->calculateRoi($side, $entryPrice, $buy ? $highest : $lowest) : 0,
            'lowest_roi'    => $validPrice ? $this->calculateRoi($side, $entryPrice, !$buy ? $highest : $lowest) : 0,
            'highest_price' => round($highest, 2),
            'lowest_price'  => round($lowest, 2)
        ];
    }

    protected function calculateRoi(string $side, int|float $entryPrice, int|float $exitPrice): int|float
    {
        $roi = ($exitPrice - $entryPrice) * 100 / $entryPrice;

        if ($side === Signal::SELL) $roi *= -1;

        return round($roi, 2);
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
}