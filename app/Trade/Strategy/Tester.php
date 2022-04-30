<?php

namespace App\Trade\Strategy;

use App\Models\Summary;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Collection\TradeCollection;
use App\Trade\Evaluation\Evaluator;
use App\Trade\Evaluation\Summarizer;
use App\Trade\HasConfig;
use App\Trade\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use JetBrains\PhpStorm\ArrayShape;

class Tester
{
    use HasConfig;

    protected array $config = [];

    final protected function getDefaultConfig(): array
    {
        return [
            'minCandles' => null,
            'startDate'  => null,
            'endDate'    => null
        ];
    }

    protected Strategy $strategy;

    protected Evaluator $evaluator;
    protected Summarizer $summarizer;

    public function __construct(string $strategyClass, array $config = [])
    {
        $this->mergeConfig($config);
        $this->strategy = $this->setupStrategy($strategyClass, $config);

        $this->evaluator = App::make(Evaluator::class, ['strategy' => $this->strategy]);
        $this->summarizer = App::make(Summarizer::class, ['strategy' => $this->strategy]);
    }

    protected function setupStrategy(string $class, array $config): Strategy
    {
        if (!\is_subclass_of($class, Strategy::class))
        {
            throw new \InvalidArgumentException('Invalid strategy class: ' . $class);
        }

        $config = array_merge_recursive_distinct($this->config, $config);

        return new $class(config: $config);
    }

    public function runStrategy(Symbol $symbol): TradeCollection
    {
        Log::execTimeStart('runStrategy');
        $trades = $this->strategy->run($symbol);
        Log::execTimeFinish('runStrategy');

        return $trades;
    }

    public function summary(TradeCollection $trades, ?Collection &$evaluations = null): Summary
    {
        $evaluations = new Collection();
        foreach ($this->evaluate($trades) as $evaluation)
        {
            $evaluations[] = $evaluation;
        }

        return $this->summarizer->summarize($evaluations);
    }

    public function progress(TradeCollection $trades, Summary &$summary = null): \Generator
    {
        foreach ($this->evaluate($trades) as $evaluation)
        {
            $this->summarizer->addEvaluation($evaluation);
            $summary = $this->summarizer->getSummary();
            yield $evaluation;
        }
    }

    protected function evaluate(TradeCollection $trades): \Generator
    {
        $evaluation = null;

        if ($first = $trades->getFirstTrade())
        {
            /** @var TradeSetup[] $pending */
            $pending = [];

            while ($next = $trades->getNextTrade($first))
            {
                $pending[] = $first;

                if ($first->isBuy() !== $next->isBuy())
                {
                    foreach ($pending as $setup)
                    {
                        $evaluate = false;

                        if ($evaluation && $evaluation->isExited())
                        {
                            $_evaluation = $this->evaluator->evaluate($setup, $next);
                            if ($evaluation->exit_timestamp <= $_evaluation->entry_timestamp)
                            {
                                yield $evaluation = $_evaluation;
                            }
                        }
                        else
                        {
                            $evaluate = true; //first evaluation or failed entry
                        }

                        if ($evaluate)
                        {
                            yield $evaluation = $this->evaluator->evaluate($setup, $next);
                        }
                    }
                    $pending = [];
                }

                $first = $next;
            }
        }
    }
}