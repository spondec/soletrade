<?php

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Indicator\Fib;
use App\Trade\Indicator\MACD;
use App\Trade\Indicator\RSI;

class BasicStrategy extends AbstractStrategy
{
    const RSI_BELOW_30 = 'RSI-BELOW_30';
    const RSI_ABOVE_70 = 'RSI-ABOVE_70';

    const RSI_BELOW_30_CONF = 'RSI-BELOW_30_CONF';
    const RSI_ABOVE_70_CONF = 'RSI-ABOVE_70_CONF';

    const MACD_CROSSOVER = 'MACD-CROSSOVER';
    const MACD_CROSSUNDER = 'MACD-CROSSUNDER';

    protected function indicatorSetup(): array
    {
        return [
            Fib::class => [
                'config' => [],
                'signal' => static function (Signal $signal, Fib $indicator, array $value): ?Signal {
                    $fib = $indicator->nearestFib($value, $closePrice = $indicator->closePrice());
                    $level = $fib['level'];
                    $fibPrice = $fib['price'];

                    if ($level !== 0 && $level !== 1000)
                    {
                        $distance = $fib['distance'];

                        if ($distance <= 1)
                        {
                            $priceBelowFib = $closePrice < $fibPrice;

                            for ($i = 1; $i <= 3; $i++)
                            {
                                $prevCandle = $indicator->candle($i);

                                if ($prevCandle->h < $fibPrice !== $priceBelowFib ||
                                    $prevCandle->l < $fibPrice !== $priceBelowFib)
                                {
                                    return null;
                                }
                            }

                            $signal->side = $side = $priceBelowFib ? Signal::SELL : Signal::BUY;
                            $signal->name = 'FIB-' . $side . '_' . $fib['level'];
                            $signal->price = $fibPrice;

                            return $signal;
                        }
                    }

                    return null;
                }
            ],
            //                        RSI::class  => [
            //                            'config' => [],
            //                            'signal' => static function (Signal $signal, RSI $indicator, int $value): ?Signal {
            //
            //                                $prev = $indicator->prev();
            //
            //                                if ($value <= 30)
            //                                {
            //                                    $signal->name = static::RSI_BELOW_30;
            //                                    $signal->side = Signal::BUY;
            //                                }
            //                                else if ($prev && $prev <= 30 && $value >= $prev)
            //                                {
            //                                    $signal->name = static::RSI_BELOW_30_CONF;
            //                                    $signal->side = Signal::BUY;
            //                                }
            //                                else if ($prev && $prev >= 70 && $value <= $prev)
            //                                {
            //                                    $signal->name = static::RSI_ABOVE_70_CONF;
            //                                    $signal->side = Signal::SELL;
            //                                }
            //                                else if ($value >= 70)
            //                                {
            //                                    $signal->name = static::RSI_ABOVE_70;
            //                                    $signal->side = Signal::SELL;
            //                                }
            //                                else
            //                                {
            //                                    return null;
            //                                }
            //
            //                                $signal->price = $indicator->closePrice();
            //
            //                                return $signal;
            //                            }
            //                        ],
            //                        MACD::class => [
            //                            'config' => [],
            //                            'signal' => static function (Signal $signal, MACD $indicator, array $value) {
            //                                if ($indicator->crossOver(static fn($v): float => $v['m'],
            //                                    static fn($v): float => $v['s']))
            //                                {
            //                                    $signal->name = static::MACD_CROSSOVER;
            //                                    $signal->side = Signal::BUY;
            //                                }
            //                                else if ($indicator->crossUnder(static fn($v): float => $v['m'],
            //                                    static fn($v): float => $v['s']))
            //                                {
            //                                    $signal->name = static::MACD_CROSSUNDER;
            //                                    $signal->side = Signal::SELL;
            //                                }
            //                                else
            //                                {
            //                                    return null;
            //                                }
            //
            //                                $signal->price = $indicator->closePrice();
            //
            //                                return $signal;
            //                            }
            //                        ]
        ];
    }

    protected function tradeSetup(): array
    {
        $stop10 = static function (TradeSetup $setup): ?TradeSetup {
            $setup->setStopPrice(1);
            $setup->setClosePrice(1 * 2);
            return $setup;
        };
        return [
            'Fib' => [
                'signals'  => [
                    Fib::class
                ],
                'callback' => static function (TradeSetup $setup): ?TradeSetup {
                    $price = $setup->price;
                    $isBuy = $setup->isBuy();

//                    $percent = $price * 0.001;
//                    $setup->price = $isBuy ? $price + $percent : $price - $percent;

                    if ($isBuy)
                    {
                        $setup->close_price = $price + $price * 0.0117;
                        $setup->stop_price = $price - $price * 0.004;
                    }
                    else
                    {
                        $setup->close_price = $price - $price * 0.0117;
                        $setup->stop_price = $price + $price * 0.004;
                    }

                    return $setup;
                }
            ],
            //            'RSI_CONF' => [
            //                'signals' => [
            //                    RSI::class => [static::RSI_ABOVE_70_CONF, static::RSI_BELOW_30_CONF]
            //                ],
            //            ],
            //            'RSI'      => [
            //                'signals' => [
            //                    RSI::class => [static::RSI_ABOVE_70, static::RSI_BELOW_30]
            //                ],
            //            ],
            //            'MACD'     => [
            //                'signals'  => [MACD::class],
            //                'callback' => $stop10
            //            ]
        ];
    }
}