<?php

namespace App\Trade\Command;

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

    protected static $isDebug = false;
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

        if (self::$isDebug = config('app.debug'))
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

        $strategy = $tester->strategy;

        if (!$options['skipUpdate'])
        {
            $this->updateSymbols($strategy);
        }

        if ($options['optimize'])
        {
            $this->runOptimizer($strategy, $tester, $args);
        }
        else
        {
            $this->runTest($tester);
        }

        $this->updateElapsedTime();

        if (self::$isDebug)
        {
            $this->sections['memUsage']->overwrite("\nMemory usage: " . Util::memoryUsage());
            $log = DB::getQueryLog();
            $time = \array_sum(\array_column($log, 'time')) / 1000;
            $this->sections['queryInfo']->overwrite("Total query time: $time"
                . "\n" .
                "Queries: " . \count($log));
        }

        $this->sections['state']->overwrite("<info>Done.</info>\n");

        return 0;
    }

    protected function newRangedTester(string $strategyClass,
                                       Symbol $symbol,
                                       ?int   $startDate,
                                       ?int   $endDate): Tester
    {
        return new Tester($strategyClass, $symbol, [
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ]);
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

    protected function runOptimizer(Strategy $strategy, Tester $tester, array $args): void
    {
        if (!$parameters = $strategy->optimizableParameters())
        {
            $this->error("Strategy {$args['strategy']} doesn't have any optimizable parameters.");
            exit(1);
        }

        $optimizer = new Optimizer($tester, $parameters);
        $optimizer->setParallelProcesses($this->processes);

        /** @var ProgressBar $progressBar */
        $progressBar = $this->helpers['progressBar']['opt'];

        $this->warn("Parameters to be optimized: "
            . implode(', ', array_keys($parameters)) .
            "\nTotal simulations: <fg=red>$optimizer->total</>");

        if ($this->ask("Do you want to proceed? (y|n)") !== 'y')
        {
            $this->info("Aborted.");
            exit(1);
        }

        if ($walkForward = ($this->ask('Do you want to run Walk Forward Analysis? (y|n)') === 'y'))
        {
            $startDateString = $this->ask('Enter the walk forward period start date (DD-MM-YYYY)');

            if (!$startDateString)
            {
                $this->error('Invalid start date.');
                exit(1);
            }

            [$walkForwardStartDate, $walkForwardEndDate] = $this->assertDateRange(
                $startDateString,
                $this->ask('Enter the walk forward period end date (DD-MM-YYYY) (optional)')
            );

            $optimizationEndDate = $tester->strategy->config('endDate');

            if (($walkForwardStartDate < $optimizationEndDate) &&
                $startDateString !== $this->option('end'))
            {
                $this->error("Walk Forward Analysis can't start before the end date of the optimization.");
                exit(1);
            }
        }

        $progressBar->start($optimizer->total);
        $summaries = $optimizer->run(callback: function (Fork $fork) use ($progressBar)
        {
            $fork->after(parent: function () use ($progressBar)
            {
                $progressBar->advance();
            });
        });

        $filtered = $this->filterOptimizedSummaries($summaries);

        $symbol = $strategy->symbol();
        $this->updateSummaryTable('optSummary',
            sprintf('%s %s Optimization Summary (%s ~ %s)',
                $strategy::name(),
                "{$symbol->exchange()::name()} $symbol->symbol $symbol->interval",
                $this->option('start'), $this->option('end')),
            $filtered->all());

        if ($walkForward)
        {
            $this->runWalkForwardAnalysis($strategy,
                $walkForwardStartDate,
                $walkForwardEndDate,
                $filtered,
                $progressBar);
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

//            //get the worst 5
//            foreach ($summaries->slice(-5, 5) as $summary)
//            {
//                $filtered[] = $summary;
//            }
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
        $tester = $this->newRangedTester($strategy::class,
            $strategy->symbol(),
            $startDate,
            $endDate);

        $summarizer = new Summarizer($tester,
            $summaries->pluck('parameters')->all()
        );
        $summarizer->setParallelProcesses($this->processes);

        $progressBar->setMaxSteps($summarizer->total);
        $progressBar->setProgress(0);

        $walkForwardSummaries = $summarizer->run(callback: function (Fork $fork) use ($progressBar)
        {
            $fork->after(parent: function () use ($progressBar)
            {
                $progressBar->advance();
            });
        });

        $parameters = $summaries->pluck('parameters')->values()->all();

        //sort by parameter order
        $walkForwardSummaries = $walkForwardSummaries->sortBy(function ($summary) use ($parameters)
        {
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
        $tester->listen('strategy_pre_run', function ()
        {
            $this->sections['state']->overwrite("<info>Running strategy...</info>\n");
        });

        $tester->listen('strategy_post_run', function (Tester          $strategyTester,
                                                       Strategy        $strategy,
                                                       TradeCollection $trades)
        {
            $this->sections['possibleTrades']->overwrite("{$trades->count()} possible trades found.");
            $this->sections['state']->overwrite("<info>Evaluating trades...</info>\n");
        });

        $tester->listen('summary_updated', function (Tester  $strategyTester,
                                                     Summary $summary,
                                                     int     $tradeCount)
        {
            $this->sections['evalTable']->clear();

            $this->sections['evaluatedTrades']->overwrite("Evaluated $tradeCount trades.\n");
            $this->updateElapsedTime();

            $this->helpers['table']['eval']
                ->setHeaders($this->getSummaryHeader())
                ->setRows([$this->getSummaryRow($summary)])
                ->render();
        });

        $tester->listen('summary_finished', function (Tester  $strategyTester,
                                                      Summary $summary,
                                                      int     $tradeCount)
        {
            if (!$tradeCount)
            {
                $this->sections['evaluatedTrades']->overwrite("No evaluations.\n");
            }
        });
    }

    protected function updateElapsedTime(): void
    {
        $elapsed = elapsed_time($this->startTime);
        $this->sections['elapsedTime']->overwrite("Elapsed time: $elapsed");
    }
}
