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

        return array_map(fn($v, $k) => [
            'm' => $v,           //macd
            's' => $macd[1][$k], //signal
            'd' => $macd[2][$k]  //divergence
        ], $macd[0], array_keys($macd[0]));
    }

    public function raw(): array
    {
        $timestamps = array_keys($this->data);
        return [
            'macd'       => array_combine($timestamps, array_column($this->data, 'm')),
            'signal'     => array_combine($timestamps, array_column($this->data, 's')),
            'divergence' => array_combine($timestamps, array_column($this->data, 'd'))
        ];
    }
}