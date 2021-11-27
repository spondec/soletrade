<?php

namespace App\Trade;

use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Evaluation\Evaluator;
use App\Trade\Evaluation\Summarizer;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use JetBrains\PhpStorm\ArrayShape;
use App\Models\Summary;

class StrategyTester
{
    use HasConfig;

    protected array $config = [];

    protected final function getDefaultConfig(): array
    {
        return [
            'maxCandles' => null,
            'startDate'  => null,
            'endDate'    => null
        ];
    }

    protected AbstractStrategy $strategy;

    protected Evaluator $evaluator;
    protected Summarizer $summarizer;

    public function __construct(protected SymbolRepository $symbolRepo, string $strategyClass, array $config = [])
    {
        $this->mergeConfig($config);
        $this->strategy = $this->setupStrategy($strategyClass, $config);

        $this->evaluator = App::make(Evaluator::class, ['strategy' => $this->strategy]);
        $this->summarizer = App::make(Summarizer::class, ['strategy' => $this->strategy]);
    }

    protected function setupStrategy(string $class, array $config): AbstractStrategy
    {
        if (!is_subclass_of($class, AbstractStrategy::class))
        {
            throw new \InvalidArgumentException('Invalid strategy class: ' . $class);
        }

        $config = array_merge_recursive_distinct($this->config, $config);

        return new $class(config: $config);
    }

    public function runStrategy(Symbol $symbol): AbstractStrategy
    {
        Log::execTimeStart('AbstractStrategy::run()');
        $this->strategy->run($symbol);
        Log::execTimeFinish('AbstractStrategy::run()');

        return $this->strategy;
    }

    #[ArrayShape(['evaluations' => "\Illuminate\Support\Collection|\App\Models\Evaluation[]", 'summary' => Summary::class])]
    public function summary(): array
    {
        return [
            'evaluations' => $evaluations = $this->evaluate(),
            'summary'     => $this->summarizer->summarize($evaluations)
        ];
    }

    /**
     * @param TradeSetup[] $trades
     */
    protected function evaluate(): Collection
    {
        $evaluations = [];
        $evaluation = null;
        if ($first = $this->strategy->getFirstTrade())
        {
            $lastCandle = $this->symbolRepo->fetchLastCandle($first->symbol);

            /** @var TradeSetup[] $pending */
            $pending = [];
            while ($next = $this->strategy->getNextTrade($first))
            {
                $pending[] = $first;

                if ($first->isBuy() != $next->isBuy())
                {
                    foreach ($pending as $setup)
                    {
                        if (!$evaluation || ($evaluation && $evaluation->exit_timestamp < $setup->price_date))
                            if ($setup->timestamp < $lastCandle->t && $next->timestamp < $lastCandle->t)
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