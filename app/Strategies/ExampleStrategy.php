<?php

namespace App\Strategies;

use App\Trade\Enum\OrderType;
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Candles;
use App\Trade\Enum\Side;
use Illuminate\Support\Collection;
use App\Trade\Strategy\Parameter\RangedSet;
use App\Trade\Strategy\Parameter\DefinedSet;
use App\Trade\Strategy\Strategy;
use App\Trade\Indicator\IndicatorDataSeries as Series;
use \App\Indicators\
{RSI
};


final class ExampleStrategy extends Strategy
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

        /**
         * You can add more config parameters below like the above and use them throughout this class.
         * It'll be pretty useful especially if you want to optimize those parameters.
         *
         * Example:
         *
         * 'shortTermPeriod' => 50,
         * 'longTermPeriod'  => 200,
         *
         */

        'rsiPeriod' => 14
    ];

    /**
     * Define optimizable parameters in key-value pairs.
     * Value must be either a RangedSet or a DefinedSet.
     *
     * Example:
     *
     * return [
     *      'shortTermPeriod' => new RangedSet(min:2,max:20, step:1),
     *      'longTermPeriod'  => new DefinedSet([2,3,5,8,13]),
     * ];
     *
     */
    public function optimizableParameters(): array
    {
        return [
            'rsiPeriod' => new RangedSet(min: 2, max: 20, step: 1),
        ];
    }

    protected function indicatorConfig(): array
    {
        return [
            [
                /**
                 * This alias must be unique amongst this file, change it if you want but
                 * don't forget the change all occurrences in this file.
                 */
                'alias'  => 'RSI',
                'class'  => RSI::class,
                'config' => [
                    'timePeriod' => $this->config('rsiPeriod'),
                ],
                /**
                 * If you want to use this as a signal, code it inside the signal function
                 * and return $signal once your condition is met, otherwise return null.
                 */
                'signal' => function (Signal $signal, RSI $indicator, Series $series): ?Signal {

                    if (crossover($series, 30))
                    {
                        return $signal->buy();
                    }

                    if (crossunder($series, 70))
                    {
                        return $signal->sell();
                    }

                    return null;
                }
            ]
        ];
    }

    protected function tradeConfig(): array
    {
        return [

            /**
             * Add any indicator aliases of required subsequent signals for the trade setup. Optional.
             * If multiple signals are added, ordering will be followed.
             *
             * E.g. => 'RSI', 'MACD'
             *
             * Above means, any MACD signal after an any RSI signal will trigger the trade setup function.
             */
            'signals' => [
                'RSI'
            ],

            /**
             * In this function, you should check the conditions for your trade.
             * If the conditions is met, return $trade with your desired configuration, otherwise null.
             *
             * Here, you can:
             *
             * Set entry, exit, target prices
             * Set order types
             * Register trade actions
             * Analyze candlestick bars
             * Check configured indicators
             * Further analyze signals
             *
             * And possibly more...
             */
            'setup'   => function (TradeSetup $trade, Candles $candles, Collection $signals): ?TradeSetup {

                return $trade;
            }
        ];
    }
}