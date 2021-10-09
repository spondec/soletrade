<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Calc;
use App\Trade\Indicator\Fib;
use Illuminate\Support\Collection;

class FibScalp extends AbstractStrategy
{
    protected function indicatorSetup(): array
    {
        return [
            Fib::class => [
                'config' => [
                    'period' => 144,
                    'levels' => [236, 382, 500, 618, 786, 702, 446, 554, 650, 886, 114, 214]
                ],
                'signal' => static function (Signal $signal, Fib $indicator, array $value): ?Signal {
                    $fib = Fib::nearestLevel($value, $closePrice = $indicator->closePrice());
                    $level = $fib['level'];
                    $fibPrice = $fib['price'];

                    if ($level !== 0 && $level !== 1000)
                    {
                        $distance = $fib['distance'];

                        if ($distance <= $indicator->config('distanceToLevel'))
                        {
                            $priceBelowFib = $closePrice < $fibPrice;

                            $bars = $indicator->config('totalBarsAfterLevel');

                            for ($i = 1; $i <= $bars; $i++)
                            {
                                $prevCandle = $indicator->candle($i);

                                if ($prevCandle->h < $fibPrice !== $priceBelowFib ||
                                    $prevCandle->l < $fibPrice !== $priceBelowFib)
                                {
                                    return null;
                                }
                            }

                            $signal->side = $side = $priceBelowFib ? Signal::SELL : Signal::BUY;
                            $signal->info = array_merge($fib, ['levels' => $indicator->config('levels'), 'prices' => $value]);
                            $signal->name = $indicator->buildSignalName(['side' => $side, 'level' => $fib['level']]);
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
                'callback' => function (TradeSetup $setup, Collection $signals): ?TradeSetup {

                    /** @var Signal $signal */
                    $signal = $signals->last();
                    $fib = $signal->info;

                    $targetLevels = Fib::targetLevels($fib['levels'], $fib['level'], $buy = $setup->isBuy());

                    $firstTarget = $targetLevels[0];
                    $targetPrice = $fib['prices'][$firstTarget];

                    /** @var Fib $fib */
                    $fib = $this->indicator($signal->indicator_id);
                    $fib->bind($setup, 'close_price', $firstTarget, timestamp: $signal->timestamp);
                    $this->bind($setup, 'price', 'last_signal_price');

                    $reward = Calc::roi($buy, $setup->price, $targetPrice) / 100;
                    $risk = $reward / 2;

                    if ($buy)
                    {
                        $this->bind($setup,
                            'stop_price',
                            'last_signal_price',
                            static fn(float $price): float => $price - $price * $risk);
                    }
                    else
                    {
                        $this->bind($setup,
                            'stop_price',
                            'last_signal_price',
                            static fn(float $price): float => $price + $price * $risk);
                    }

                    return $setup;
                }
            ]
        ];
    }
}