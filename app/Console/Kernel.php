<?php

namespace App\Console;

use App\Trade\Exception\PrintableException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Throwable;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    protected ?ConsoleSectionOutput $printableExceptionSection = null;

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Command');
        $this->load(__DIR__ . '/../Trade/Command');

        require base_path('routes/console.php');
    }

    public function handle($input, $output = null)
    {
        $this->printableExceptionSection = $output?->section();

        return parent::handle($input, $output);
    }

    protected function renderException($output, Throwable $e)
    {
        if ($this->isPrintable($e))
        {
            $this->printableExceptionSection->writeln('<fg=red>' . $e->getMessage() . '</>');
        }
        else
        {
            parent::renderException($output, $e);
        }
    }

    protected function isPrintable(Throwable $e): bool
    {
        return $e instanceof PrintableException && $this->printableExceptionSection;
    }
}
