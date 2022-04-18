<?php

namespace App\Strategies;

use App\Models\OrderType;
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Candles;
use App\Trade\Side;
use Illuminate\Support\Collection;
use App\Trade\Strategy\Strategy;
use \App\Indicators\
{
    SMA
};

class SMACross extends Strategy
{
    protected array $config = [
        /**
         * The amount of candles to run the strategy.
         * If the strategy requires less than the default,
         * this can be set to a lower value so that the strategy runs faster.
         */
        'maxCandles' => 1000,

        'trades'     => [
            /**
             * When true, multiple trades to the same direction will be disregarded.
             */
            'oppositeOnly' => false,
        ],
        'evaluation' => [
            'loop'     => [
                /**
                 * Maximum trade duration in minutes, 0 to disable.
                 * Exceeding trades will be stopped at close price.
                 */
                'timeout'     => 0,
                /**
                 * When true, reverse side setups will be used as an exit trade.
                 * When false, exit trades will be through the target price.
                 */
                'closeOnExit' => true,
            ],
            /**
             * If set, trades will be evaluated at this interval. E.g. 15m, 5m, 1m.
             */
            'interval' => null
        ],
        /**
         * Trade commission cut ratio, each trade costs two of this fee
         * and added to the final ROI when testing the strategy.
         */
        'feeRatio'   => 0.001
    ];

    protected function indicatorConfig(): array
    {
        return [
            SMA::class => [

                'config' => [
                    'progressiveInterval' => NULL,
                    'timePeriod'          => 8,
                ],
                'signal' => function (Signal $signal, SMA $indicator, mixed $value): ?Signal {

                    return null;
                }
            ]
        ];
    }

    protected function tradeConfig(): array
    {
        return [

            /**
             * Add any indicator classes of required signals for the trade setup. Optional.
             * If multiple signals are added, ordering will be followed.
             *
             * E.g. => RSI::class, MACD::class
             *
             * Above means, any MACD signal after an any RSI signal
             * will trigger the trade setup function for further consideration.
             */
            'signals' => [
                SMA::class
            ],

            /**
             * In this function, you should check the conditions for your trade.
             * If the conditions is met, return a new TradeSetup with your desired configuration, otherwise null.
             */
            'setup'   => function (TradeSetup $trade, Candles $candles, Collection $signals): ?TradeSetup {

                /**
                 * Here, you can:
                 *
                 * Set entry, exit, target prices
                 * Set order types
                 * Register trade actions
                 * Analyze candlestick bars
                 * Check configured indicators
                 * Further analyze signals
                 */

                return null;
            }
        ];
    }
}