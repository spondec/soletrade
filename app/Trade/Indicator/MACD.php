<?php

namespace App\Trade\Indicator;

class MACD extends AbstractIndicator
{
    protected array $config = [
        'fastPeriod'   => 12,
        'slowPeriod'   => 26,
        'signalPeriod' => 9,
    ];

    protected function run(): array
    {
        $macd = \trader_macd($this->closes(),
            $this->config['fastPeriod'],
            $this->config['slowPeriod'],
            $this->config['signalPeriod']);

        return [
            'macd'       => $this->combineTimestamps($macd[0]),
            'signal'     => $this->combineTimestamps($macd[1]),
            'divergence' => $this->combineTimestamps($macd[2]),
        ];
    }
}