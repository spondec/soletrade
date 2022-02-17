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
use function array_merge_recursive_distinct;

class Tester
{
    use HasConfig;

    protected array $config = [];

    final protected function getDefaultConfig(): array
    {
        return [
            'maxCandles' => null,
            'startDate'  => null,
            'endDate'    => null
        ];
    }

    protected Strategy $strategy;

    protected Evaluator $evaluator;
    protected Summarizer $summarizer;

    public function __construct(protected SymbolRepository $symbolRepo, string $strategyClass, array $config = [])
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

    #[ArrayShape([
        'evaluations' => "\Illuminate\Support\Collection|\App\Models\Evaluation[]",
        'summary'     => Summary::class
    ])]
    public function summary(TradeCollection $trades): array
    {
        return [
            'evaluations' => $evaluations = $this->evaluate($trades),
            'summary'     => $this->summarizer->summarize($evaluations)
        ];
    }

    protected function evaluate(TradeCollection $trades): Collection
    {
        $evaluations = [];
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
                                $evaluations[] = $evaluation = $_evaluation;
                            }
                        }
                        else
                        {
                            $evaluate = true; //first evaluation or failed entry
                        }

                        if ($evaluate)
                        {
                            $evaluations[] = $evaluation = $this->evaluator->evaluate($setup, $next);
                        }
                    }
                    $pending = [];
                }

                $first = $next;
            }
        }

        return new Collection($evaluations);
    }
}