<?php

namespace App\Trade\Indicator;

use App\Trade\CandleCollection;
use App\Trade\Indicator\Helpers\CanCross;

class MACD extends AbstractIndicator
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

        if (!$macd) return [];
        return array_map(static fn($v, $k) => [
            'macd'       => $v,
            'signal'     => $macd[1][$k],
            'divergence' => $macd[2][$k]
        ], $macd[0], array_keys($macd[0]));
    }

    public function raw(): array
    {
        $timestamps = $this->data()->keys()->all();
        $data = $this->data()->all();

        return [
            'macd'       => array_combine($timestamps, array_column($data, 'macd')),
            'signal'     => array_combine($timestamps, array_column($data, 'signal')),
            'divergence' => array_combine($timestamps, array_column($data, 'divergence'))
        ];
    }
}