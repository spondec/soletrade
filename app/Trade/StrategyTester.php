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

            $updater = $symbol->exchange()->updater();

            if ($symbol->last_update < time() - 3600)
                $updater->update($symbol);

            $updater->updateByInterval(interval: '1m',
                filter: static fn(Symbol $v) => $v->symbol === $symbol->symbol &&
                    $v->exchange_id === $symbol->exchange_id);

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

        $lowestRois = [];
        $highestRois = [];

        foreach ($paired as $pair)
        {
            if ($pair['entry']['valid_price'] || !$pair['result']['ambiguous'])
            {
                $realized = $pair['result']['realized_roi'];
                $balance = $this->cutCommission($balance, 0.002);
                $pnl = $this->calculatePnl($balance, $roi[] = $realized);
                $balance += $pnl;
                $count++;

                $highestRois[] = (float)$pair['result']['highest_roi'];
                $lowestRois[] = (float)$pair['result']['lowest_roi'];
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
                else if ($realized > 0)
                {
                    $profit++;
                }
            }
        }

        return [
            'roi'               => $roi = round($balance - 100, 2),
            'avg_roi'           => $count ? round($roi / $count, 2) : 0,
            'avg_highest_roi'   => $count && $highestRois ? round($avgHighestRoi = array_sum($highestRois) / count($highestRois), 2) : 0,
            'avg_lowest_roi'    => $count && $lowestRois ? round($avgLowestRoi = array_sum($lowestRois) / count($lowestRois), 2) : 0,
            'risk_reward_ratio' => $count && $highestRois && $lowestRois ? round(abs($avgHighestRoi / $avgLowestRoi), 2) : 0,
            'profit'            => $profit,
            'loss'              => $loss,
            'ambiguous'         => $ambiguous
        ];
    }

    protected function calculatePnl(float $balance, float $roi): float|int
    {
        return $balance * $roi / 100;
    }

    protected function cutCommission(float|int $balance, float|int $ratio): int|float
    {
        return $balance - abs($balance * $ratio);
    }
}