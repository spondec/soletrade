<?php

namespace App\Console\Commands;

use App\Models\Summary;
use App\Repositories\SymbolRepository;
use App\Trade\Exchange\Exchange;
use App\Trade\Strategy\Tester;
use App\Trade\Util;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

class StrategyTester extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trade:strategy-test {strategy} {symbol} {interval} {exchange} {--start=} {--end=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests specified strategy on specified symbol.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $options = $this->options();
        $args = $this->arguments();
        $startTime = \time();

        if ($debug = config('app.debug'))
        {
            \DB::enableQueryLog();
        }

        if (!strategy_exists($name = $args['strategy']))
        {
            $this->error("Strategy $name not found.");
            $this->info('Available strategies:');
            foreach (get_strategies() as $name => $class)
            {
                $this->info($name);
            }
            return 1;
        }

        try
        {
            $startDate = $options['start']
                ? Carbon::createFromFormat('d-m-Y', $options['start'])
                    ->setTime(0, 0)
                    ->getTimestamp() * 1000
                : null;

            $endDate = $options['end']
                ? Carbon::createFromFormat('d-m-Y', $options['end'])
                    ->setTime(23, 59, 59)
                    ->getTimestamp() * 1000
                : null;
        } catch (InvalidFormatException $e)
        {
            $this->error($e->getMessage());
            $this->error("Invalid date format.");
            $this->error("Use format d-m-Y as in 19-11-2021.");
            return 1;
        }

        if ($startDate && !$endDate)
        {
            $endDate = Carbon::now()->setTime(0, 0)->getTimestamp() * 1000;
        }

        $tester = new Tester(get_strategy_class($name), [
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ]);

        $repo = new SymbolRepository();
        $symbol = $repo->fetchSymbol(Exchange::from($args['exchange']), $args['symbol'], $args['interval']);

        if (!$symbol)
        {
            $this->error("Symbol {$args['symbol']} {$args['interval']} not found on {$args['exchange']}.");
            return 1;
        }

        /** @var ConsoleOutput $output */
        $output = $this->output->getOutput();
        $section = $output->section();
        $table = new Table($section);
        $table->setHorizontal();

        $this->info("Running strategy...");
        $trades = $tester->runStrategy($symbol);

        if ($count = $trades->count())
        {
            $this->info("$count possible trades found.");
            $section->overwrite("<info>Evaluating trades...</info>");
        }
        else
        {
            $this->info("No possible trades found.");
        }

        $e = 0;
        /** @var Summary $summary */
        foreach ($tester->progress($trades, $summary) as $ignored)
        {
            $e++;
            $section->clear();
            $elapsed = elapsed_time($startTime);
            $section->overwrite("<info>Evaluated $e trades.\nElapsed time: $elapsed</info>");

            $table->setHeaders([
                'ROI',
                'Avg. ROI',
                'Avg. Profit',
                'Avg. Loss',
                'Reward/Risk',
                'Success Ratio',
                'Profit',
                'Loss',
                'Ambiguous',
                'Failed'
            ])->setRows([
                [
                    Util::formatRoi($summary->roi),
                    Util::formatRoi($summary->avg_roi),
                    $summary->avg_profit_roi . '%',
                    $summary->avg_loss_roi . '%',
                    $summary->risk_reward_ratio,
                    $summary->success_ratio . '%',
                    $summary->profit,
                    $summary->loss,
                    $summary->ambiguous,
                    $summary->failed
                ]
            ])->render();

            if ($debug)
            {
                $section->writeln('Memory usage: ' . Util::memoryUsage());
            }
        }

        if (!$e)
        {
            $section->overwrite('<info>Trades could not be evaluated.</info>');
        }

        if ($debug)
        {
            $log = \DB::getQueryLog();
            $time = \array_sum(\array_column($log, 'time')) / 1000;
            $this->line("Total query time: $time");
            $this->line("Queries: " . \count($log));
        }
        return 0;
    }
}
