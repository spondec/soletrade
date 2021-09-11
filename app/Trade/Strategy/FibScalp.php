<?php

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Indicator\Fib;

class FibScalp extends AbstractStrategy
{
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
                            $indicator->bind($signal, 'price', $fib['level']);

                            return $signal;
                        }
                    }

                    return null;
                }
            ]
        ];
    }

    protected function tradeSetup(): array
    {
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
                        $setup->close_price = $price + $price * 0.015;
                        $setup->stop_price = $price - $price * 0.005;
                    }
                    else
                    {
                        $setup->close_price = $price - $price * 0.015;
                        $setup->stop_price = $price + $price * 0.005;
                    }

                    return $setup;
                }
            ]
        ];
    }
}