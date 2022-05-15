<?php

namespace App\Trade\Strategy;

use App\Models\Summary;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Collection\TradeCollection;
use App\Trade\Evaluation\Evaluator;
use App\Trade\Evaluation\Summarizer;
use App\Trade\HasConfig;
use Illuminate\Support\Collection;

class Tester
{
    use HasConfig;

    public readonly Strategy $strategy;
    protected array $config = [];
    protected Evaluator $evaluator;

    public function __construct(string $strategyClass, Symbol $symbol, array $config = [])
    {
        $this->mergeConfig($config);
        $this->strategy = $this->newStrategy($strategyClass, $symbol, $config);

        $this->evaluator = new Evaluator($this->strategy);
    }

    protected function newStrategy(string $class, Symbol $symbol, array $config): Strategy
    {
        if (!\is_subclass_of($class, Strategy::class))
        {
            throw new \InvalidArgumentException('Invalid strategy class: ' . $class);
        }

        $config = array_merge_recursive_distinct($this->config, $config);

        return new $class(symbol: $symbol, config: $config);
    }

    public function runStrategy(): TradeCollection
    {
        $this->strategy->updateSymbols();
        return $this->strategy->run();
    }

    public function summary(TradeCollection $trades, ?Collection &$evaluations = null): Summary
    {
        $summarizer = $this->newSummarizer();
        $evaluations = new Collection();
        foreach ($this->evaluate($trades) as $evaluation)
        {
            $evaluations[] = $evaluation;
        }

        return $summarizer->summarize($evaluations);
    }

    protected function newSummarizer(): Summarizer
    {
        return new Summarizer($this->strategy);
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

    public function summarize(TradeCollection $trades, Summary &$summary = null): \Generator
    {
        $summarizer = $this->newSummarizer();
        foreach ($this->evaluate($trades) as $evaluation)
        {
            $summarizer->addEvaluation($evaluation);
            $summary = $summarizer->getSummary();
            yield $evaluation;
        }
    }

    final protected function getDefaultConfig(): array
    {
        return [
            'minCandles' => null,
            'startDate'  => null,
            'endDate'    => null
        ];
    }
}