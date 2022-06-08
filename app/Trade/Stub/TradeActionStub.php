<?php

namespace App\Trade\Stub;

use App\Trade\Action\Handler;

class TradeActionStub extends Creator
{
    public function getStubFileName(): string
    {
        return 'trade.strategy.action.stub';
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
        return ['action_class', 'action_config'];
    }

    protected function modifyParams(array $params): array
    {
        $action = $params['action'];

        /** @var class-string<Handler> $actionClass */
        $actionClass = "\App\Trade\Action\\$action";

        $params['action_class'] = "$action::class";
        $params['action_config'] = StrategyIndicatorStub::getConfigExport($actionClass::getStubConfig());

        return $params;
    }

    protected function modifyContent(string $content): string
    {
        return "\n $content \n";
    }
}