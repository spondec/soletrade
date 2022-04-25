<?php

namespace App\Indicators;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;

class Combined extends Indicator
{
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
        'indicators' => []
    ];
    protected array $variableConfigKeys = ['indicators'];
    /**
     * @var Indicator[]
     */
    protected array $indicators = [];

    public function indicator(string $alias): Indicator
    {
        return $this->indicators[$alias];
    }

    protected function calculate(CandleCollection $candles): array
    {
        $data = [];
        foreach ($this->config('indicators') as $alias => $indicator)
        {
            /** @var Indicator $instance */
            $instance = $this->indicators[$alias] = new $indicator['class'](
                symbol: $this->symbol,
                candles: $candles,
                config: $indicator['config']
            );

            foreach ($instance->data() as $k => $value)
            {
                $data[$k][$alias] = $value;
            }
        }

        return $data;
    }
}