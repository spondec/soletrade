<?php

declare(strict_types=1);

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
                'callback' => function (TradeSetup $setup): ?TradeSetup {


                    if ($setup->isBuy())
                    {
                        $this->bind($setup,
                            'close_price',
                            'last_signal_price',
                            static fn(float $price): float => $price + $price * 0.017);
                        $this->bind($setup,
                            'stop_price',
                            'last_signal_price',
                            static fn(float $price): float => $price - $price * 0.005);
                    }
                    else
                    {
                        $this->bind($setup,
                            'close_price',
                            'last_signal_price',
                            static fn(float $price): float => $price - $price * 0.017);
                        $this->bind($setup,
                            'stop_price',
                            'last_signal_price',
                            static fn(float $price): float => $price + $price * 0.005);
                    }

                    return $setup;
                }
            ]
        ];
    }
}