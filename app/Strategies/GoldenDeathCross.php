<?php

namespace App\Strategies;

use App\Trade\Strategy\Parameter\DefinedSet;
use App\Indicators\
{Combined, MA
};
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Candles;
use App\Trade\Enum\Side;
use App\Trade\Strategy\Parameter\RangedSet;
use App\Trade\Strategy\Strategy;
use Illuminate\Support\Collection;


final class GoldenDeathCross extends Strategy
{
    protected array $config = [
        /**
         * The amount of candles to run the strategy.
         * If the strategy requires less than the default,
         * this can be set to a lower value so that the strategy runs faster.
         */
        'minCandles' => 1000,

        'trades'     => [
            /**
             * When true, multiple trades to the same direction will be disregarded.
             */
            'oppositeOnly'  => true,
            /**
             * When true, waits for the next candle to open before trading.
             */
            'permanentOnly' => true,
        ],
        'evaluation' => [
            'loop'     => [
                /**
                 * Maximum trade duration in minutes, 0 to disable.
                 * Exceeding trades will be stopped at close price.
                 */
                'timeout'     => 0,
                /**
                 * When true, an opposite side setup will be used as an exit trade.
                 * When false, exit trades will be through the target price.
                 */
                'closeOnExit' => true,
            ],
            /**
             * If set, trades will be evaluated at this interval. E.g. 15m, 5m, 1m.
             * Provides more accurate evaluation at the cost of performance.
             * Lowest intervals can really slow down the strategy testing.
             */
            'interval' => null
        ],
        /**
         * Trade commission ratio. This will be reflected on the final ROI when tested. Disabled by default.
         * Most exchanges charges between 0.0004(0.04%) and 0.001(0.1%).
         */
        'feeRatio'   => 0.0000,

        'shortTermPeriod' => 55,
        'longTermPeriod'  => 21
    ];

    public function optimizableParameters(): array
    {
        return [
//            'shortTermPeriod' => new RangedSet(2, 100, 1),
//            'longTermPeriod'  => new RangedSet(2, 100, 1),

'shortTermPeriod' => new DefinedSet($this->fibSequence(10)),
'longTermPeriod'  => new DefinedSet($this->fibSequence(10))
        ];
    }

    public function fibSequence($n)
    {
        $fib = [0, 1];
        for ($i = 2; $i <= $n; $i++)
        {
            $fib[$i] = $fib[$i - 1] + $fib[$i - 2];
        }
        return array_slice($fib, 3);
    }

    protected function indicatorConfig(): array
    {
        return [
            [
                'alias'  => 'maCross',
                'class'  => Combined::class,
                'config' => [
                    'indicators' => [
                        0 => [
                            'alias'  => 'shortTerm',
                            'class'  => MA::class,
                            'config' => [
                                'timePeriod' => $this->config('shortTermPeriod'),
                            ],
                        ],
                        1 => [
                            'alias'  => 'longTerm',
                            'class'  => MA::class,
                            'config' => [
                                'timePeriod' => $this->config('longTermPeriod'),
                            ],
                        ],
                    ],
                ],
                'signal' => function (Signal $signal, Combined $indicator, mixed $value): ?Signal {

                    if ($indicator->crossOver('shortTerm', 'longTerm'))
                    {
                        $signal->name = 'Golden Cross';
                        $signal->side = Side::BUY;

                        return $signal;
                    }

                    if ($indicator->crossUnder('shortTerm', 'longTerm'))
                    {
                        $signal->name = 'Death Cross';
                        $signal->side = Side::SELL;

                        return $signal;
                    }

                    return null;
                }
            ]
        ];
    }

    protected function tradeConfig(): array
    {
        return [
            'signals' => [
                'maCross'
            ],
            'setup'   => function (TradeSetup $trade, Candles $candles, Collection $signals): ?TradeSetup {

                $trade->setStopPrice(0.02);
                return $trade;
            }
        ];
    }
}