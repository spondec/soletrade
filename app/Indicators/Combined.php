<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Helpers\CanCross;
use App\Trade\Indicator\Indicator;

class Combined extends Indicator
{
    use CanCross;

    protected array $config = [
        /**
         * Example:
         * [
         *      'sma_8' => [
         *              'class' => \App\Indicators\SMA::class,
         *              'config' => ['timeFrame' => 8]
         *      ],
         *      'ema_20' => [
         *              'class' => \App\Indicators\EMA::class,
         *              'config' => ['timeFrame' => 20]
         *      ]
         * ]
         */
        'indicators' => [

        ]
    ];
    protected array $variableConfigKeys = ['indicators'];

    protected function calculate(CandleCollection $candles): array
    {
        $data = [];
        foreach ($this->config('indicators') as $alias => $config)
        {
            /** @var Indicator $indicator */
            $indicator = new $config['class'](
                symbol: $this->symbol,
                candles: $candles,
                config: $config['config']
            );

            foreach ($indicator->data() as $k => $value)
            {
                $data[$k][$alias] = $value;
            }
        }

        return $data;
    }
}