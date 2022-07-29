<?php

namespace App\Trade\Console\Command;

use App\Models\Summary;
use App\Models\Symbol;
use App\Trade\Collection\SummaryCollection;
use App\Trade\Collection\TradeCollection;
use App\Trade\HasInstanceEvents;
use App\Trade\Repository\SymbolRepository;
use App\Trade\Strategy\Process\Optimizer;
use App\Trade\Strategy\Process\Summarizer;
use App\Trade\Strategy\Strategy;
use App\Trade\Strategy\Tester;
use App\Trade\Util;
use Illuminate\Support\Facades\DB;
use Spatie\Fork\Fork;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class StrategyTester extends TradeCommand
{
    use HasInstanceEvents;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trade:strategy-test {strategy} {symbol} {interval} {exchange} {--start=} {--end=} {--optimize} {--skipUpdate}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests specified strategy on a specified symbol.';

    /**
     * @var ConsoleSectionOutput[]
     */
    protected array $sections = [];
    /**
     * @var Table[]
     */
    protected array $helpers = [];

    protected int $startTime;

    protected int $processes;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(SymbolRepository $repo)
    {
        $options = $this->options();
        $args = $this->arguments();
        $this->startTime = \time();
        $this->processes = config('trade.options.concurrentProcesses');

        if ($isDebugging = config('app.debug'))
        {
            DB::enableQueryLog();
        }

        $strategyClass = $this->assertStrategyClass($args['strategy']);
        $symbol = $this->assertSymbol($repo, $args);

        [$startDate, $endDate] = $this->assertDateRange($options['start'] ?? '01-01-1970', $options['end']);

        $tester = $this->newRangedTester($strategyClass,
            $symbol,
            $startDate,
            $endDate);

        $this->initSections();

        if (!$options['skipUpdate'])
        {
            $this->updateSymbols($tester->strategy);
        }

        if ($options['optimize'])
        {
            $this->assertParameters($tester->strategy);

            $walkForward = $this->askWalkForward($tester);

            if ($walkForward['consecutive']['enabled'])
            {
                $this->runConsecutiveWalkForwardOptimization(
                    $tester,
                    $walkForward['consecutive']['optimizationInterval'],
                    $walkForward['consecutive']['walkForwardInterval']
                );
            }
            else
            {
                $this->runOptimization(
                    $tester,
                    $walkForward['enabled'],
                    $walkForward['startDate'],
                    $walkForward['endDate']
                );
            }
        }
        else
        {
            $this->runTest($tester);
        }

        $this->updateElapsedTime();

        if ($isDebugging)
        {
            $this->sections['memUsage']->overwrite("\nMemory usage: " . Util::memoryUsage());
            $log = DB::getQueryLog();
            $time = \array_sum(\array_column($log, 'time')) / 1000;
            $this->sections['queryInfo']->overwrite(
                sprintf("Total query time: $time\nQueries: %s", \count($log))
            );
        }

        $this->sections['state']->overwrite("<info>Done.</info>\n");

        return 0;
    }

    protected function newRangedTester(string $strategyClass,
                                       Symbol $symbol,
                                       ?int   $startDate,
                                       ?int   $endDate): Tester
    {
        $strategy = new $strategyClass(symbol: $symbol, config: [
            'startDate' => $startDate,
            'endDate'   => $endDate
        ]);
        return new Tester($strategy);
    }

    protected function initSections(): void
    {
        $this->sections['state'] = $this->newOutputSection();
        $this->sections['possibleTrades'] = $this->newOutputSection();
        $this->sections['evaluatedTrades'] = $this->newOutputSection();
        $this->sections['elapsedTime'] = $this->newOutputSection();
        $this->sections['memUsage'] = $this->newOutputSection();
        $this->sections['queryInfo'] = $this->newOutputSection();

        $this->sections['evalTable'] = $this->newOutputSection();
        $this->sections['optSummaryTable'] = $this->newOutputSection();
        $this->sections['walkForwardSummaryTable'] = $this->newOutputSection();
        $this->sections['optProgressBar'] = $this->newOutputSection();

        $this->helpers['progressBar']['opt'] = new ProgressBar($this->sections['optProgressBar']);
        $this->helpers['table']['eval'] = new Table($this->sections['evalTable']);
        $this->helpers['table']['eval']->setHorizontal();
        $this->helpers['table']['optSummary'] = new Table($this->sections['optSummaryTable']);
        $this->helpers['table']['walkForwardSummary'] = new Table($this->sections['walkForwardSummaryTable']);
    }

    protected function updateSymbols(Strategy $strategy): void
    {
        $this->warn("\nUpdating symbols... This may take a while for the first time, please be patient...");
        $strategy->updateSymbols();
        $this->info("Done.\n");
    }

    protected function askWalkForward(Tester $tester): array
    {
        if ($hasWalkForward = ($this->ask('Do you want to run Walk Forward Analysis? (y|n)') === 'y'))
        {
            if ($isConsecutive = ($this->ask('Walk forward consecutively?') === 'y'))
            {
                $this->warn('Optimization Period');
                $optimizationInterval = $this->askConsecutiveInterval();

                $this->warn('Walk Forward Period');
                $walkForwardInterval = $this->askConsecutiveInterval();
            }
            else
            {
                if (!$startDateString = $this->ask('Enter the walk forward period start date (DD-MM-YYYY)'))
                {
                    $this->error('Invalid start date.');
                    exit(1);
                }

                [$walkForwardStartDate, $walkForwardEndDate] = $this->assertDateRange(
                    $startDateString,
                    $this->ask('Enter the walk forward period end date (DD-MM-YYYY) (optional)')
                );

                $optimizationEndDate = $tester->strategy->config('endDate');

                if (
                    $walkForwardStartDate < $optimizationEndDate &&
                    $startDateString !== $this->option('end')
                )
                {
                    $this->error("Walk Forward Analysis can't start before the end date of the optimization.");
                    exit(1);
                }
            }
        }

        return [
            'enabled'     => $hasWalkForward,
            'startDate'   => $walkForwardStartDate ?? null,
            'endDate'     => $walkForwardEndDate ?? null,
            'consecutive' => [
                'enabled'              => $isConsecutive ?? false,
                'optimizationInterval' => $optimizationInterval ?? null,
                'walkForwardInterval'  => $walkForwardInterval ?? null,
            ],
        ];
    }

    protected function askConsecutiveInterval(): int
    {
        return match ($this->askDayMonthYear())
            {
                'day' => 86400,
                'month' => 86400 * 28,
                'year' => 86400 * 365,
                default => throw new \Exception('Invalid choice. Available: day, month or year')
            } * $this->askPeriodCoefficient();
    }

    protected function askDayMonthYear(): string
    {
        return $this->choice('Choose a period', ['day', 'month', 'year']);
    }

    protected function askPeriodCoefficient(): int
    {
        return (int)$this->ask("Enter the period coefficient (e.g. 1, 7, 15, 30...)");
    }

    protected function runOptimization(
        Tester $tester,
        bool   $walkForward = false,
        int    $walkForwardStart = null,
        int    $walkForwardEnd = null,
        bool   $verbose = true
    ): void
    {
        $strategy = $tester->strategy;

        $parameters = $this->assertParameters($strategy);

        $optimizer = new Optimizer($tester, $parameters);
        $optimizer->setParallelProcesses($this->processes);

        /** @var ProgressBar $progressBar */
        $progressBar = $this->helpers['progressBar']['opt'];

        if ($verbose)
        {
            $this->warn("Parameters to be optimized: "
                . implode(', ', array_keys($parameters)) .
                "\nTotal simulations: <fg=red>$optimizer->total</>");

            if ($this->ask("Do you want to proceed? (y|n)") !== 'y')
            {
                $this->info("Aborted.");
                exit(1);
            }
        }

        $progressBar->start($optimizer->total);
        $summaries = $optimizer->run(callback: function (Fork $fork) use ($progressBar) {
            $fork->after(parent: function () use ($progressBar) {
                $progressBar->advance();
            });
        });

        $filtered = $this->filterOptimizedSummaries($summaries);

        $symbol = $strategy->symbol();
        $this->updateSummaryTable('optSummary',
            sprintf('%s %s Optimization Summary (%s ~ %s)',
                $strategy::name(),
                "{$symbol->exchange()::name()} $symbol->symbol $symbol->interval",
                Util::dateFormat($strategy->config('startDate')), Util::dateFormat($strategy->config('endDate'))),
            $filtered->all());

        if ($walkForward)
        {
            $this->runWalkForwardAnalysis(
                $strategy,
                $walkForwardStart,
                $walkForwardEnd,
                $filtered,
                $progressBar
            );
        }

        $this->sections['possibleTrades']->clear();
        $this->sections['evaluatedTrades']->clear();
    }

    protected function filterOptimizedSummaries(SummaryCollection $summaries): SummaryCollection
    {
        $filtered = [];
        if ($summaries->count() > 10)
        {
            //get the best 10
            foreach ($summaries->slice(0, 10) as $summary)
            {
                $filtered[] = $summary;
            }
        }
        else
        {
            foreach ($summaries as $summary)
            {
                $filtered[] = $summary;
            }
        }
        return new SummaryCollection($filtered);
    }

    protected function updateSummaryTable(string $tableName, string $title, array $summaries): void
    {
        $rows = [];
        foreach ($summaries as $k => $summary)
        {
            $rows[$k] = $this->getSummaryRow($summary);
        }

        $this->getTable($tableName)
            ->setHeaderTitle($title)
            ->setHeaders($this->getSummaryHeader())
            ->setRows($rows)
            ->render();
    }

    protected function getSummaryRow(Summary $summary): array
    {
        $params = '';

        foreach ($summary->parameters as $name => $value)
        {
            if (is_array($value))
            {
                $value = Util::varExport($value);
            }
            $params .= "$name: $value ";
        }

        return [
            Util::formatRoi($summary->roi),
            Util::formatRoi($summary->avg_roi),
            $summary->avg_profit_roi . '%',
            $summary->avg_loss_roi . '%',
            $summary->risk_reward_ratio,
            $summary->success_ratio . '%',
            $summary->profit,
            $summary->loss,
            $summary->ambiguous,
            $summary->failed,
            $params
        ];
    }

    protected function getTable(string $name): Table
    {
        return $this->helpers['table'][$name];
    }

    /**
     * @return string[]
     */
    protected function getSummaryHeader(): array
    {
        return [
            'ROI',
            'Avg. ROI',
            'Avg. Profit',
            'Avg. Loss',
            'Reward/Risk',
            'Success',
            'Profit',
            'Loss',
            'Ambiguous',
            'Failed',
            'Parameters'
        ];
    }

    protected function runWalkForwardAnalysis(Strategy          $strategy,
                                              int               $startDate,
                                              int               $endDate,
                                              SummaryCollection $summaries,
                                              ProgressBar       $progressBar): void
    {
        $tester = $this->newRangedTester(
            $strategy::class,
            $strategy->symbol(),
            $startDate,
            $endDate
        );

        $summarizer = new Summarizer(
            $tester,
            $summaries->pluck('parameters')->all()
        );

        $summarizer->setParallelProcesses($this->processes);

        $progressBar->setMaxSteps($summarizer->total);
        $progressBar->setProgress(0);

        $walkForwardSummaries = $summarizer->run(callback: function (Fork $fork) use ($progressBar) {
            $fork->after(parent: function () use ($progressBar) {
                $progressBar->advance();
            });
        });

        $parameters = $summaries->pluck('parameters')->values()->all();

        //sorts by parameter order
        $walkForwardSummaries = $walkForwardSummaries->filter()->sortBy(function (Summary $summary) use ($parameters) {
            return array_search($summary->parameters, $parameters);
        });

        $this->updateSummaryTable('walkForwardSummary',
            sprintf("Walk Forward Period (%s ~ %s)",
                Util::dateFormat($startDate),
                Util::dateFormat($endDate)),
            $walkForwardSummaries->all());
    }

    protected function runTest(Tester $tester): void
    {
        $this->registerTesterEvents($tester);
        $trades = $tester->runStrategy();
        $tester->summary($trades);
    }

    protected function registerTesterEvents(Tester $tester): void
    {
        $tester->listen('strategy_pre_run',
            function () {
                $this->sections['state']->overwrite("<info>Running strategy...</info>\n");
            }
        );

        $tester->listen('strategy_post_run',
            function (Tester $tester, Strategy $strategy, TradeCollection $trades) {
                $this->sections['possibleTrades']->overwrite("{$trades->count()} possible trades found.");
                $this->sections['state']->overwrite("<info>Evaluating trades...</info>\n");
            }
        );

        $tester->listen('summary_updated',
            function (Tester $tester, Summary $summary, int $tradeCount) {
                $this->sections['evalTable']->clear();

                $this->sections['evaluatedTrades']->overwrite("Evaluated $tradeCount trades.\n");
                $this->updateElapsedTime();

                $this->helpers['table']['eval']
                    ->setHeaders($this->getSummaryHeader())
                    ->setRows([$this->getSummaryRow($summary)])
                    ->render();
            }
        );

        $tester->listen('summary_finished',
            function (Tester $tester, Summary $summary, int $tradeCount) {
                if (!$tradeCount)
                {
                    $this->sections['evaluatedTrades']->overwrite("No evaluations.\n");
                }
            }
        );
    }

    protected function updateElapsedTime(): void
    {
        $elapsed = elapsed_time($this->startTime);
        $this->sections['elapsedTime']->overwrite("Elapsed time: $elapsed");
    }

    protected function runConsecutiveWalkForwardOptimization(Tester $tester,
                                                             int    $optimizationPeriod,
                                                             int    $walkForwardPeriod): void
    {
        $strategy = $tester->strategy;
        $symbol = $strategy->symbol();

        $startDate = $strategy->config('startDate')
            ?: $symbol->firstCandle()?->t
            ?? throw new \UnexpectedValueException('No start date found.');

        $endDate = $strategy->config('endDate')
            ?: $symbol->lastCandle()?->t
            ?? throw new \UnexpectedValueException('No end date found.');

        $optStart = $startDate;

        while ($optStart < $endDate)
        {
            $strategy->mergeConfig([
                'startDate' => $optStart,
                'endDate'   => $optEnd = $optStart + $optimizationPeriod * 1000
            ]);

            $this->runOptimization(
                $tester,
                true,
                $optEnd,
                $optStart = $optEnd + $walkForwardPeriod * 1000,
                false
            );

            $this->initSections();
        }
    }

    public function assertParameters(Strategy $strategy): array
    {
        if (!$parameters = $strategy->optimizableParameters())
        {
            $this->error("Strategy {$strategy::name()} doesn't have any optimizable parameters.");
            exit(1);
        }
        return $parameters;
    }
}