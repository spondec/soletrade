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
        /** @noinspection PhpUndefinedFunctionInspection */
        $macd = \trader_macd($this->closes(),
            $this->config['fastPeriod'],
            $this->config['slowPeriod'],
            $this->config['signalPeriod']);

        if (!$macd) return [];
        return array_map(static fn($v, $k) => [
            'm' => $v,           //macd
            's' => $macd[1][$k], //signal
            'd' => $macd[2][$k]  //divergence
        ], $macd[0], array_keys($macd[0]));
    }

    public function raw(): array
    {
        $timestamps = $this->data()->keys()->all();
        $data = $this->data()->all();

        return [
            'macd'       => array_combine($timestamps, array_column($data, 'm')),
            'signal'     => array_combine($timestamps, array_column($data, 's')),
            'divergence' => array_combine($timestamps, array_column($data, 'd'))
        ];
    }
}