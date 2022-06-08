<?php

namespace App\Trade\Strategy\Process;

use App\Trade\Collection\SummaryCollection;
use App\Trade\Process\Parallel\Process;
use App\Trade\Strategy\Tester;
use Illuminate\Support\Facades\DB;
use Spatie\Fork\Fork;

abstract class Summarization extends Process
{
    protected function setupFork(Fork $fork): Fork
    {
        return $fork
            ->before(fn () => DB::connection('mysql')->reconnect());
    }

    protected function newStrategySummaryJob(Tester $tester, array $config): \Closure
    {
        $strategy = clone $tester->strategy;
        $tester = clone $tester;

        return static function () use ($config, $strategy, $tester)
        {
            $strategy->mergeConfig($config);
            $trades = $strategy->run();
            $summary = $tester->summary($trades);
            $summary->parameters = $config;

            return $summary;
        };
    }

    protected function handleJobResults(array $results): SummaryCollection
    {
        return new SummaryCollection($results);
    }

    public function run(?\Closure $callback = null): SummaryCollection
    {
        return parent::run($callback);
    }
}
