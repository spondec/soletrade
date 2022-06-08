<?php

namespace App\Trade\Strategy\Process;

use App\Trade\Strategy\Tester;

class Summarizer extends Summarization
{
    public function __construct(protected Tester $tester, protected array $configs)
    {
        parent::__construct();
    }

    protected function getTotal(): int
    {
        return count($this->configs);
    }

    protected function getJobs(): array
    {
        $jobs = [];

        foreach ($this->configs as $config)
        {
            $jobs[] = $this->newStrategySummaryJob($this->tester, $config);
        }

        return $jobs;
    }
}