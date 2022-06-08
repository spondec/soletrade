<?php

namespace App\Trade\Config;

use App\Models\Signature;

class TradeConfig extends Config
{
    public readonly bool $withSignals;

    public function __construct(
        public readonly array $signals,
        public readonly \Closure $setup,
        public readonly Signature $signature
    )
    {
        $this->withSignals = !empty($signals);
    }

    /**
     * @return string[]
     */
    public function getSignalIndicatorAliases(): array
    {
        $indicators = [];
        foreach ($this->signals as $key => $indicator) {
            $indicators[] = \is_array($indicator) ? $key : $indicator;
        }
        return $indicators;
    }
}
