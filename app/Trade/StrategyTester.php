<?php

namespace App\Trade;

use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;

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
                    'result' => (new Evaluator($entry, $trade))->evaluate(),
                    'entry'  => $entry,
                    'exit'   => $trade
                ];

                $entry = $trade;
            }
        }

        return ['trades' => $paired, 'summary' => $this->summarize($paired)];
    }

    protected function summarize(array $paired): array
    {
        $balance = 100;

        $ambiguous = 0;
        $profit = 0;
        $loss = 0;
        $count = 0;

        foreach ($paired as $pair)
        {
            if ($pair['entry']['valid_price'] || !$pair['result']['ambiguous'])
            {
                $realized = $pair['result']['realized_roi'];
                $pnl = $this->calculatePnl($balance, $roi[] = $realized);
                $balance += $pnl;
                $count++;
            }

            if ($pair['result']['ambiguous'])
            {
                $ambiguous++;
            }
            else
            {
                if ($realized < 0)
                {
                    $loss++;
                }
                else
                {
                    $profit++;
                }
            }
        }

        return [
            'roi'       => $roi = round($balance - 100, 2),
            'avg_roi'   => $count ? round($roi / $count, 2) : 0,
            'profit'    => $profit,
            'loss'      => $loss,
            'ambiguous' => $ambiguous
        ];
    }

    protected function calculatePnl(float $balance, float $roi): float|int
    {
        return $balance * $roi / 100;
    }
}