<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Calc;
use App\Trade\Indicator\ATR;
use App\Trade\Indicator\Fib;
use App\Trade\Strategy\TradeAction\MoveStop;
use Illuminate\Support\Collection;

class FibScalp extends AbstractStrategy
{
    protected string $progressiveInterval = '15m';

    protected function helperIndicators(): array
    {
        return [
            ATR::class => [
                'interval' => $this->progressiveInterval
            ]
        ];
    }

    protected function indicatorSetup(): array
    {
        return [
            Fib::class => [
                'config' => [
                    'period'              => 144,
                    'atrMultiplier'       => 1,
                    'totalBarsAfterLevel' => 3,
                    'levels'              => [236, 382, 500, 618, 786, 886, 114],
                    'progressiveInterval' => $this->progressiveInterval
                ],
                'signal' => function (Signal $signal, Fib $indicator, array $value): ?Signal {

                    $candle = $indicator->candle();
                    $timestamp = $candle->t;
                    $price = (float)$candle->c;

                    $fib = Fib::nearestLevel($value, $price);
                    $fibLevel = $fib['level'];
                    $fibPrice = $fib['price'];

                    if ($fibLevel !== 0 && $fibLevel !== 1000)
                    {
                        $atr = $this->helperIndicator(ATR::class)->data()[$timestamp];

                        if (abs($fibPrice - $price) <= $atr)
                        {
                            $priceBelowFib = $price < $fibPrice;

                            $bars = $indicator->config('totalBarsAfterLevel');

                            for ($i = 1; $i <= $bars; $i++)
                            {
                                $prevCandle = $indicator->candle($i * -1);

                                if ($prevCandle->h < $fibPrice !== $priceBelowFib ||
                                    $prevCandle->l < $fibPrice !== $priceBelowFib)
                                {
                                    return null;
                                }
                            }

                            $signal->side = $side = $priceBelowFib ? Signal::SELL : Signal::BUY;
                            $signal->info = array_merge($fib, ['prices' => $value]);
                            $signal->name = $indicator->buildSignalName(['side' => $side, 'level' => $fibLevel]);
                            $indicator->bind($signal, 'price', $fibLevel);

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

                    $targetLevels = Fib::targetLevels($fib['prices'], $fib['level'], $isBuy = $setup->isBuy());

                    $firstTarget = $targetLevels[0];
                    $targetPrice = $fib['prices'][$firstTarget];

                    /** @var Fib $fib */
                    $fib = $this->indicator($signal->indicator_id);
                    $fib->bind($setup, 'close_price', $firstTarget, timestamp: $setup->timestamp);
                    $this->bind($setup, 'price', 'last_signal_price');

                    $targetRoi = Calc::roi($isBuy, $entryPrice = $setup->price, $targetPrice);
                    $reward = $targetRoi / 100;
                    $risk = $reward / 2;
                    $setup->size = 100;

                    $this->newAction($setup, MoveStop::class, [
                        'target'         => ['roi' => $targetRoi / 2],
                        'new_stop_price' => $entryPrice
                    ]);

                    $this->bind($setup,
                        'stop_price',
                        'last_signal_price',
                        $isBuy
                            ? static fn(float $price): float => $price - $price * $risk
                            : static fn(float $price): float => $price + $price * $risk);
                    return $setup;
                }
            ]
        ];
    }
}