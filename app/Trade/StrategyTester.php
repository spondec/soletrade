<?php

namespace App\Trade;

use App\Models\Evaluation;
use App\Models\Model;
use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Evaluation\Evaluator;
use App\Trade\Evaluation\Summary;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class StrategyTester
{
    use HasConfig;

    protected array $config = [
        'strategy' => [
            'maxCandles' => null,
            'startDate'  => null,
            'endDate'    => null
        ],
        'summary'  => [

        ]
    ];

    protected Evaluator $evaluator;

    public function __construct(protected SymbolRepository $symbolRepo, array $config = [])
    {
        $this->mergeConfig($config);
        $this->evaluator = App::make(Evaluator::class);
    }

    public function runStrategy(Symbol $symbol, string $strategyClass, array $config = []): AbstractStrategy
    {
        $strategy = $this->setupStrategy($strategyClass, $config);

        Log::execTimeStart('CandleUpdater::update()');
        $this->updateCandles($symbol);
        Log::execTimeFinish('CandleUpdater::update()');

        Log::execTimeStart('AbstractStrategy::run()');
        $strategy->run($symbol);
        Log::execTimeFinish('AbstractStrategy::run()');

        return $strategy;
    }

    public function summary(Collection $trades, array $config = [])
    {
        return $this->summarize($this->evaluate($trades),
            array_merge_recursive_distinct($this->config['summary'], $config));
    }

    protected function setupStrategy(string $class, array $config): AbstractStrategy
    {
        if (!is_subclass_of($class, AbstractStrategy::class))
        {
            throw new \InvalidArgumentException('Invalid strategy class: ' . $class);
        }

        $config = array_merge_recursive_distinct($this->config['strategy'], $config);

        return new $class(config: $config);
    }

    protected function updateCandles(Symbol $symbol): void
    {
        $updater = $symbol->exchange()->updater();

        if ($symbol->last_update <= (time() - 3600) * 1000)
        {
            $updater->update($symbol);
        }

        $updater->updateByInterval(interval: '1m',
            filter: static fn(Symbol $v) => $v->symbol === $symbol->symbol &&
                $v->exchange_id === $symbol->exchange_id);
    }

    /**
     * @param TradeSetup[]|Signal[] $trades
     */
    protected function evaluate(Collection $trades): Collection
    {
        $evaluations = new Collection();
        $lastCandle = $this->symbolRepo->fetchLastCandle($trades->first()->symbol);

        foreach ($trades as $trade)
        {
            if (!isset($entry))
            {
                $entry = $trade;
                continue;
            }

            if ($entry->side !== $trade->side &&
                $entry->timestamp < $lastCandle->t &&
                $trade->timestamp < $lastCandle->t)
            {
                $evaluations[] = $this->evaluator->evaluate($entry, $trade);

                $entry = $trade;
            }
        }

        return $evaluations;
    }

    /**
     * @param Collection|TradeSetup[]|Signal[] $trades
     */
    protected function summarize(Collection $trades, array $config = []): Summary
    {
        return new Summary($trades, $config);
    }
}