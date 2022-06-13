<?php

namespace App\Trade\Strategy;

use App\Models\Summary;
use App\Models\TradeSetup;
use App\Trade\Collection\TradeCollection;
use App\Trade\Evaluation\Evaluator;
use App\Trade\Evaluation\Summarizer;
use App\Trade\HasInstanceEvents;
use Illuminate\Support\Collection;

class Tester
{
    use HasInstanceEvents;

    protected array $events = [
        'strategy_pre_run',
        'strategy_post_run',

        'summary_updated',
        'summary_finished',
    ];

    protected Evaluator $evaluator;

    public function __clone(): void
    {
        $this->strategy = clone $this->strategy;
    }

    public function __construct(public Strategy $strategy)
    {
        $this->strategy->mergeConfig(['minCandles' => null]);
        $this->evaluator = new Evaluator($this->strategy);
    }

    public function runStrategy(): TradeCollection
    {
        $this->fireEvent('strategy_pre_run', $this->strategy);

        $trades = $this->strategy->run();

        $this->fireEvent('strategy_post_run', $this->strategy, $trades);

        return $trades;
    }

    public function summary(TradeCollection $trades, ?Collection &$evaluations = null): Summary
    {
        $evaluations = new Collection();
        foreach ($this->summarize($trades, $summary) as $evaluation)
        {
            $evaluations[] = $evaluation;
        }

        return $summary ?? new Summary();
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

        $tradeCount = 0;
        foreach ($this->evaluate($trades) as $evaluation)
        {
            $summarizer->addEvaluation($evaluation);
            $summary = $summarizer->getSummary();

            $tradeCount++;
            $this->fireEvent('summary_updated', $summary, $tradeCount);

            yield $evaluation;
        }

        $this->fireEvent('summary_finished', $summary ?? new Summary(), $tradeCount);
    }
}