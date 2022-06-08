<?php

namespace App\Trade\Stub;

use App\Trade\Util;

class StrategyIndicatorStub extends Creator
{
    public function getStubFileName(): string
    {
        return 'trade.strategy.indicator.stub';
    }

    public function getDestinationDir(): ?string
    {
        return null;
    }

    public function getFileName(): ?string
    {
        return null;
    }

    public function getPlaceholders(): array
    {
        return ['indicator', 'alias', 'config'];
    }

    protected function modifyParams(array $params): array
    {
        $indicator = $params['indicator'];
        $combined = $params['combined'] ?? null;

        $default = Util::indicatorConfig($indicator);

        if ($combined) {
            $default['indicators'] = $combined->map(fn (string $indicator): array => [
                'alias'  => "{{ {$indicator}_alias }}",
                'class'  => "{{ {$indicator}_class }}",
                'config' => Util::indicatorConfig($indicator),
            ])->all();
        }

        $params['config'] = static::getConfigExport(\array_merge($default, $params['config']));
        $params['alias'] = "'$indicator'";

        return $params;
    }

    public static function getConfigExport(array $config): string
    {
        $export = Util::varExport($config);
        $export = str($export)
            ->explode("\n")
            ->map(fn (string $line): string => "\t\t\t\t$line");
        $export->pop();
        $export->shift();

        return "[\n".$export->implode("\n")."\n\t\t\t\t]";
    }
}
