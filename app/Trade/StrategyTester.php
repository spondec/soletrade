<?php

namespace App\Trade;

use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Evaluation\Evaluator;
use App\Trade\Evaluation\Summarizer;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use JetBrains\PhpStorm\ArrayShape;

class StrategyTester
{
    use HasConfig;

    protected array $config = [
        'strategy'   => [
            'maxCandles' => null,
            'startDate'  => null,
            'endDate'    => null
        ],
        'evaluator'  => [],
        'summarizer' => []
    ];

    protected Evaluator $evaluator;
    protected Summarizer $summarizer;

    public function __construct(protected SymbolRepository $symbolRepo, array $config = [])
    {
        $this->mergeConfig($config);

        $this->evaluator = App::make(Evaluator::class, ['config' => $config['evaluator'] ?? []]);
        $this->summarizer = App::make(Summarizer::class, ['config' => $config['summarizer'] ?? []]);
    }

    public function runStrategy(Symbol $symbol, string $strategyClass, array $config = []): AbstractStrategy
    {
        $strategy = $this->setupStrategy($strategyClass, $config);

        Log::execTimeStart('AbstractStrategy::run()');
        $strategy->run($symbol);
        Log::execTimeFinish('AbstractStrategy::run()');

        return $strategy;
    }

    #[ArrayShape(['evaluations' => "\Illuminate\Support\Collection|\App\Models\Evaluation[]", 'summary' => "\App\Models\Summary"])]
    public function summary(Collection $trades): array
    {
        return [
            'evaluations' => $evaluations = $this->evaluate($trades),
            'summary'     => $this->summarizer->summarize($evaluations)
        ];
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
}