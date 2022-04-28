<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Helpers\CanCross;
use App\Trade\Indicator\Indicator;
use App\Trade\Util;

class Combined extends Indicator
{
    use CanCross;

    protected array $config = [
        /**
         * Example:
         * [
         *       [
         *              'alias' => 'sma_8
         *              'class' => \App\Indicators\SMA::class,
         *              'config' => ['timeFrame' => 8]
         *       ],
         *       [
         *              'alias' => 'ema_20'
         *              'class' => \App\Indicators\EMA::class,
         *              'config' => ['timeFrame' => 20]
         *       ]
         * ]
         */
        'indicators' => [

        ]
    ];
    protected array $variableConfigKeys = ['indicators'];

    protected function calculate(CandleCollection $candles): array
    {
        $data = [];
        $indicators = $this->config('indicators');

        if ($duplicates = Util::getDuplicates(array_column($indicators, 'alias')))
        {
            throw new \LogicException('Duplicate indicator aliases: ' . implode(', ', $duplicates));
        }

        foreach ($indicators as $config)
        {
            /** @var Indicator $indicator */
            $indicator = new $config['class'](
                symbol: $this->symbol,
                candles: $candles,
                config: $config['config']
            );

            $alias = $config['alias'];
            foreach ($indicator->data() as $k => $value)
            {
                $data[$k][$alias] = $value;
            }
        }

        return $data;
    }
}