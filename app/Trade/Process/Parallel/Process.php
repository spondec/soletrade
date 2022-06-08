<?php

declare(strict_types=1);

namespace App\Trade\Process\Parallel;

use Spatie\Fork\Fork;

abstract class Process
{
    public readonly int $total;
    protected int $processes;

    public function __construct()
    {
        $this->total = $this->getTotal();
    }

    abstract protected function getTotal(): int;

    public function setParallelProcesses(int $amount = 8): void
    {
        if ($amount < 1)
        {
            throw new \InvalidArgumentException('Processes must be at least 1.');
        }

        $this->processes = $amount;
    }

    public function run(?\Closure $callback = null): mixed
    {
        $jobs = $this->getJobs();

        $results = [];

        $fork = $this->newFork($callback);

        foreach (array_chunk($jobs, $this->processes) as $chunk)
        {
            array_push($results, ...$fork->run(...$chunk));
        }

        return $this->handleJobResults($results);
    }

    /**
     * @return Closure[]
     */
    abstract protected function getJobs(): array;

    private function newFork(?\Closure $callback = null): Fork
    {
        $fork = $this->setupFork(Fork::new());

        if ($callback)
        {
            $callback($fork);
        }

        return $fork;
    }

    protected function setupFork(Fork $fork): Fork
    {
        return $fork;
    }

    protected function handleJobResults(array $results): mixed
    {
        return null;
    }
}
