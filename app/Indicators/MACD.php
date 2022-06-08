<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Helper\CanCross;
use App\Trade\Indicator\Indicator;

final class MACD extends Indicator
{
    use CanCross;

    protected array $config = [
        'fastPeriod'   => 12,
        'slowPeriod'   => 26,
        'signalPeriod' => 9,
    ];

    protected function calculate(CandleCollection $candles): array
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        $macd = \trader_macd($candles->closes(),
                             $this->config['fastPeriod'],
                             $this->config['slowPeriod'],
                             $this->config['signalPeriod']);

        if (! $macd)
        {
            return [];
        }

        return \array_map(static fn ($v, $k) => [
            'macd'       => $v,
            'signal'     => $macd[1][$k],
            'divergence' => $macd[2][$k],
        ], $macd[0], \array_keys($macd[0]));
    }
}
