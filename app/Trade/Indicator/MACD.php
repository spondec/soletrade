<?php

namespace App\Trade\Indicator;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Helpers\CanCross;
use Illuminate\Support\Collection;

class MACD extends Indicator
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
        return \array_map(static fn($v, $k) => [
            'macd'       => $v,
            'signal'     => $macd[1][$k],
            'divergence' => $macd[2][$k]
        ], $macd[0], \array_keys($macd[0]));
    }

    public function raw(Collection $data): array
    {
        $timestamps = $data->keys()->all();

        return [
            'macd'       => \array_combine($timestamps, \array_column($data->all(), 'macd')),
            'signal'     => \array_combine($timestamps, \array_column($data->all(), 'signal')),
            'divergence' => \array_combine($timestamps, \array_column($data->all(), 'divergence'))
        ];
    }
}