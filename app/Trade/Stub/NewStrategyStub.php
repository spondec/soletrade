<?php

namespace App\Trade\Stub;

class NewStrategyStub extends Creator
{
    public function getStubFileName(): string
    {
        return 'trade.strategy.stub';
    }

    public function getDestinationDir(): string
    {
        return base_path('app/Strategies/');
    }

    public function getFileName(): string
    {
        return $this->params['name'].'.php';
    }

    public function getPlaceholders(): array
    {
        return [
            'name',
            'signals',
            'indicator_stubs',
            'indicators',
            'actions',
            'action_stubs',
            'use',
        ];
    }

    protected function modifyParams(array $params): array
    {
        $indicators = $params['indicators'];
        $combined = $params['combined'];

        $useIndicators = $combined->first()
            ? $indicators->merge($combined)->merge(['Combined'])->unique()
            : $indicators;

        $params['indicators'] = $indicators->implode(', ');
        $params['actions'] = $actionsImplode = $params['actions']->implode(', ');

        $params['use'] = ($useIndicators->first() ? "use \App\Indicators\ { {$useIndicators->implode(', ')} };" : '')
            ."\n".
            ($actionsImplode ? "use \App\Trade\Action\ { $actionsImplode };" : '');

        $params['action_stubs'] = $params['action_stubs']->implode("\n");
        $params['indicator_stubs'] = $params['indicator_stubs']->implode(",\n\t\t\t");
        $params['signals'] = $params['signals']->map(fn (string $s) => "'$s'")->implode(', ');

        return $params;
    }

    protected function modifyContent(string $content): string
    {
        foreach ($this->params['combined'] as $indicator)
        {
            $content = \str_replace(
                [
                    "'{{ {$indicator}_alias }}'",
                    "'{{ {$indicator}_class }}'",
                ],
                [
                    "'$indicator'", $indicator.'::class',
                ],
                $content);
        }

        return $content;
    }
}
