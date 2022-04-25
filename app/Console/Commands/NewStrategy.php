<?php

namespace App\Console\Commands;

use App\Models\Symbol;
use App\Trade\Collection\CandleCollection;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class NewStrategy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trade:strategy {name} {--indicators=} {--signals=} {--actions=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new strategy.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dir = base_path('app/');
        $stubsPath = $dir . 'stubs/';

        $name = ucfirst($this->argument('name'));
        $signals = str($this->option('signals'))
            ->explode(',')
            ->filter()
            ->map('trim');

        $actions = str($this->option('actions'))
            ->explode(',')
            ->filter()
            ->map('trim');

        $indicators = str($this->option('indicators'))
            ->explode(',')
            ->filter()
            ->map('trim')
            ->merge($signals)
            ->unique();

        $files = new Filesystem();

        if (!$files->exists($indicatorStubPath = $stubsPath . 'trade.strategy.indicator.stub'))
        {
            throw new FileNotFoundException("Stub file not found at $indicatorStubPath");
        }

        $indicatorStub = $files->get($indicatorStubPath);
        $indicatorStubs = new Collection();

        foreach ($indicators as $indicator)
        {
            if (!indicator_exists($indicator))
            {
                $this->error("Indicator $indicator does not exist.");
                return 1;
            }

            $config = (new (INDICATOR_NAMESPACE . $indicator)(new Symbol(), new CandleCollection()))->config();

            $indicatorStubs[] = str_replace(
                [
                    '{{ indicator }}',
                    '{{ alias }}',
                    '{{ config }}'
                ],
                [
                    $indicator,
                    "'$indicator'",
                    "[\n{$this->getArrayExport($config)}\n\t\t\t\t]"
                ],
                $indicatorStub);
        }
        if (!$files->exists($actionStubPath = $stubsPath . 'trade.strategy.action.stub'))
        {
            throw new FileNotFoundException("Stub file not found at $actionStubPath");
        }

        $actionStub = $files->get($actionStubPath);

        $actionStubs = new Collection();
        foreach ($actions as $action)
        {

            /** @noinspection PhpUndefinedMethodInspection */
            $actionStubs[] = str_replace(
                [
                    '{{ action_class }}',
                    '{{ action_config }}'
                ],
                [
                    "$action::class",
                    "[\n{$this->getArrayExport(("\App\Trade\Action\\$action")::getStubConfig())}\n\t\t\t\t]",
                ],
                $actionStub);

        }

        if (!$files->exists($strategyStubPath = $stubsPath . 'trade.strategy.stub'))
        {
            throw new FileNotFoundException("Stub file not found at {$strategyStubPath}");
        }

        $strategyStub = $files->get($strategyStubPath);
        $strategyStub = str_replace(
            [
                '{{ name }}',
                '{{ signals }}',
                '{{ indicator_stubs }}',
                '{{ indicators }}',
                '{{ actions }}',
                '{{ action_stubs }}',
                '{{ use }}'
            ],
            [
                $name,
                $signals->map(fn(string $s) => "'$s'")->implode(', '),
                $indicatorStubs->implode(",\n\t\t\t"),
                $_indicators = $indicators->implode(', '),
                $_actions = $actions->implode(', '),
                $actionStubs->implode("\n"),
                ($_indicators ? "use \App\Indicators\ { $_indicators };" : '')
                . "\n" .
                ($_actions ? "use \App\Trade\Action\ { $_actions };" : '')
            ],
            $strategyStub
        );

        if ($files->exists(STRATEGY_DIR . "$name.php"))
        {
            $this->error("Strategy $name already exists.");
            return 1;
        }

        $files->put(STRATEGY_DIR . "$name.php", $strategyStub);
        $this->info("Strategy $name created.");

        return 0;
    }

    protected function getArrayExport(mixed $config): string
    {
        $export = str($this->varExport($config))
            ->explode("\n");
        $export->pop();
        $export->shift();
        return $export->map(fn(string $line) => "\t\t\t\t\t" . trim($line))->implode("\n");
    }

    public function varExport($expression): string
    {
        $export = var_export($expression, TRUE);
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [NULL, ']$1', ' => ['], $array);
        $export = implode(PHP_EOL, array_filter(["["] + $array));
        return $export;
    }
}
