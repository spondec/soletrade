<?php

declare(strict_types=1);

namespace App\Trade\Strategy\Process;

use App\Trade\Collection\SummaryCollection;
use App\Trade\Strategy\Optimization\Parameter\ParameterSet;
use App\Trade\Strategy\Tester;
use BenTools\CartesianProduct\CartesianProduct;

class ParallelOptimizer extends ParallelSummarization
{
    protected CartesianProduct $combinator;

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

        parent::__construct();
    }

    protected function getTotal(): int
    {
        return $this->combinator->count();
    }

    protected function getJobs(): array
    {
        $jobs = [];

        foreach ($this->combinator as $combination)
        {
            $jobs[] = $this->newStrategySummaryJob($this->tester, $combination);
        }

        return $jobs;
    }

    protected function handleJobResults(array $results): SummaryCollection
    {
        return parent::handleJobResults($results)
            ->filter()
            ->sort(fn($a, $b) => $a['roi'] < $b['roi']); //sort by ROI desc;
    }
}