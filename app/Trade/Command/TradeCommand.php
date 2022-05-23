<?php

namespace App\Trade\Command;

use App\Models\Symbol;
use App\Trade\Exchange\Exchange;
use App\Trade\Repository\SymbolRepository;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

abstract class TradeCommand extends Command
{
    protected function assertStrategyClass(string $strategyName): string
    {
        if (!strategy_exists($name = $strategyName))
        {
            $this->error("Strategy $name not found.");
            $this->info('Available strategies:');
            foreach (get_strategies() as $name => $class)
            {
                $this->info($name);
            }
            exit(1);
        }

        return get_strategy_class($name);
    }

    /**
     * @param array $options
     *
     * @return int[]
     */
    protected function getDateRange(array $options): array
    {
        try
        {
            $startDate = $options['start']
                ? $this->newDate($options['start'])
                    ->setTime(0, 0)
                    ->getTimestamp() * 1000
                : null;

            $endDate = $options['end']
                ? $this->newDate($options['end'])
                    ->setTime(23, 59, 59)
                    ->getTimestamp() * 1000
                : null;
        } catch (InvalidFormatException $e)
        {
            $this->error($e->getMessage());
            $this->error("Invalid date format.");
            $this->error("Use format d-m-Y as in 19-11-2021.");
            exit(1);
        }

        if ($startDate && !$endDate)
        {
            $endDate = Carbon::now()->setTime(0, 0)->getTimestamp() * 1000;
        }

        return [$startDate, $endDate];
    }

    protected function assertSymbol(SymbolRepository $repo, array $args): Symbol
    {
        $symbol = $repo->fetchSymbol(Exchange::from($args['exchange']),
            $args['symbol'],
            $args['interval']);

        if (!$symbol)
        {
            $this->error("Symbol {$args['symbol']} {$args['interval']} not found on {$args['exchange']}.");
            exit(1);
        }

        return $symbol;
    }

    protected function newDate(string $date): Carbon
    {
        return Carbon::createFromFormat('d-m-Y', $date);
    }

    protected function newOutputSection(): ConsoleSectionOutput
    {
        /** @var ConsoleOutput $output */
        $output = $this->output->getOutput();
        return $output->section();
    }
}