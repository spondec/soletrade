<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CompareLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:compare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compares the last two query logs.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
       $logs = Storage::allFiles('logs/query');

        $last = $this->getLog(array_pop($logs));
        $before = $this->getLog(array_pop($logs));

        $diff = array_diff($last, $before);
        dump($diff);

        return 0;
    }

    protected function getLog(string $path): array
    {
        $log = json_decode(Storage::get($path), true);

        return array_column($log, 'query');
    }
}
