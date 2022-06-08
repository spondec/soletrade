<?php

namespace App\Trade\Command;

use App\Trade\Stub\StrategyIndicatorStub;
use App\Trade\Stub\TradeActionStub;
use Illuminate\Support\Collection;

class StrategyCreator extends TradeCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trade:strategy {name} {--indicators=} {--signals=} {--actions=} {--combined=}';

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
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(\App\Trade\Stub\NewStrategyStub $newStrategy)
    {
        $name = \ucfirst($this->argument('name'));
        $signals = str($this->option('signals'))
            ->explode(',')
            ->filter()
            ->map('trim')
            ->unique();

        $actions = str($this->option('actions'))
            ->explode(',')
            ->filter()
            ->map('trim');

        $combined = str($this->option('combined'))
            ->explode(',')
            ->filter()
            ->map('trim');

        $indicators = str($this->option('indicators'))
            ->explode(',')
            ->filter()
            ->map('trim')
            ->merge($signals)
            ->filter(fn (string $i) => 'Combined' !== $i)
            ->unique();

        $indicatorStubs = $this->getIndicatorStubs($indicators, $combined);
        $actionStubs = $this->getActionStubs($actions);

        $newStrategy->setParams([
            'name' => $name,
            'signals' => $signals,
            'indicator_stubs' => $indicatorStubs,
            'indicators' => $indicators,
            'actions' => $actions,
            'action_stubs' => $actionStubs,
            'combined' => $combined,
        ]);

        if ($newStrategy->isFileExists()) {
            $this->error("Strategy $name already exists.");

            return 1;
        }

        $newStrategy->apply()->save();
        $this->info("Strategy $name created.");

        return 0;
    }

    protected function getIndicatorStubs(Collection $indicators, Collection $combined): Collection
    {
        $indicatorStubs = new Collection();

        if ($combined->first()) {
            $indicatorStubs[] = $this->getIndicatorStub('Combined', $combined)->apply()->content;
        }

        foreach ($indicators as $indicator) {
            $indicatorStubs[] = $this->getIndicatorStub($indicator)->apply()->content;
        }

        return $indicatorStubs;
    }

    protected function getIndicatorStub(string $indicator,
                                        ?Collection $combined = null,
                                        array $config = []): StrategyIndicatorStub
    {
        if (!indicator_exists($indicator)) {
            $this->error("Indicator $indicator does not exist.");
            exit(1);
        }

        return $this->newStrategyIndicatorStub()
            ->setParams([
                'indicator' => $indicator,
                'alias' => "'$indicator'",
                'config' => $config,
                'combined' => $combined,
            ]);
    }

    protected function newStrategyIndicatorStub(): StrategyIndicatorStub
    {
        return \App::make(StrategyIndicatorStub::class);
    }

    protected function getActionStubs(Collection $actions): Collection
    {
        $actionStubs = new Collection();

        foreach ($actions as $action) {
            $actionStubs[] = $this->newTradeActionStub()
                ->setParams([
                    'action' => $action,
                ])
                ->apply()
                ->content;
        }

        return $actionStubs;
    }

    protected function newTradeActionStub(): TradeActionStub
    {
        return \App::make(TradeActionStub::class);
    }
}
