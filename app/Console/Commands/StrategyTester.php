<?php

namespace App\Console\Commands;

use App\Console\Util;
use App\Models\Summary;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use App\Trade\Exchange\Exchange;
use App\Trade\Strategy\Tester;
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
        $startTime = time();

        if (!strategy_exists($name = $args['strategy']))
        {
            $this->error("Strategy $name not found.");
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

        $this->info("Running strategy...");
        $trades = $tester->runStrategy($symbol);

        if ($count = $trades->count())
        {
            $this->info("$count possible trades found.");
        }

        $section->overwrite("<info>Evaluating trades...</info>");
        $e = 0;
        /** @var Summary $summary */
        foreach ($tester->progress($trades, $summary) as $ignored)
        {
            $section->clear();
            $e++;
            $elapsed = Calc::elapsedTime($startTime);
            $section->overwrite("<info>Evaluated $e trades.\nElapsed time: $elapsed</info>");
            $table->setHeaders([
                'ROI',
                'Avg. ROI',
                'Avg. Profit',
                'Avg. Loss',
                'Risk/Reward',
                'Success Ratio',
                'Profit',
                'Loss',
                'Ambiguous',
                'Failed'
            ]);
            $table->setRows([
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
            ]);
            $table->render();
        }
        return 0;
    }
}