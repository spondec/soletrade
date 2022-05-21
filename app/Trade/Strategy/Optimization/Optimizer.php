<?php

declare(strict_types=1);

namespace App\Trade\Strategy\Optimization;

use App\Trade\Collection\SummaryCollection;
use App\Trade\Strategy\Parameter\ParameterSet;
use App\Trade\Strategy\Tester;
use BenTools\CartesianProduct\CartesianProduct;
use Closure;
use Illuminate\Support\Facades\DB;
use Spatie\Fork\Fork;

class Optimizer
{
    protected CartesianProduct $combinator;

    public readonly int $total;
    protected int $processes;

    /**
     * @param Tester         $tester
     * @param ParameterSet[] $parameters
     */
    public function __construct(protected Tester $tester, public readonly array $parameters)
    {
        $this->combinator = new CartesianProduct(
            array_map(
                static fn(ParameterSet $paramSet) => $paramSet->values(), $this->parameters)
        );

        $this->total = $this->combinator->count();
    }

    public function setProcesses(int $amount = 8): void
    {
        if ($amount < 1)
        {
            throw new \InvalidArgumentException('Processes must be at least 1.');
        }

        $this->processes = $amount;
    }

    public function run(?Closure $callback = null): SummaryCollection
    {
        $jobs = [];

        foreach ($this->combinator as $combination)
        {
            $strategy = clone $this->tester->strategy;
            $tester = clone $this->tester;

            $jobs[] = static function () use ($combination, $strategy, $tester) {

                $strategy->mergeConfig($combination);
                $trades = $strategy->run();
                $summary = $tester->summary($trades);
                $summary->parameters = $combination;

                return $summary;
            };
        }

        $results = [];
        foreach (array_chunk($jobs, $this->processes) as $chunk)
        {
            $results[] = $this->newFork($callback)->run(...$chunk);
        }

        return (new SummaryCollection(...$results))
            ->filter()
            ->sort(fn($a, $b) => $a['roi'] < $b['roi']); //sort by ROI desc
    }

    protected function newFork(?Closure $callback = null): Fork
    {
        $fork = Fork::new()
            ->before(fn() => DB::connection('mysql')->reconnect());

        if ($callback)
        {
            $callback($fork);
        }

        return $fork;
    }
}