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

    protected function indicatorConfig(): array
    {
        return [
            Fib::class => [
                'config' => [
                    'period'              => 144,
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
                        $multiplier = 3;

                        $isPriceBelowFibLevel = $price < $fibPrice;
                        $distance = $isPriceBelowFibLevel ? $fibPrice - $price : $price - $fibPrice;

                        if ($distance > 0 && $distance <= $atr * $multiplier)
                        {
                            $totalBarsAfterLevel = 2;

                            for ($i = 1; $i <= $totalBarsAfterLevel; $i++)
                            {
                                $prevCandle = $indicator->candle($i * -1);

                                if ($prevCandle->h < $fibPrice !== $isPriceBelowFibLevel ||
                                    $prevCandle->l < $fibPrice !== $isPriceBelowFibLevel)
                                {
                                    return null;
                                }
                            }

                            $signal->side = $side = $isPriceBelowFibLevel ? Signal::SELL : Signal::BUY;
                            $signal->info = ['prices' => $value, 'nearest' => $fib];
                            $signal->name = $indicator->buildSignalName(['side' => $side, 'level' => $fibLevel]);
                            $signal->price = $fibPrice;

                            return $signal;
                        }
                    }

                    return null;
                }
            ]
        ];
    }

    protected function tradeConfig(): array
    {
        return [
            'signals'  => [
                Fib::class
            ],
            'callback' => function (TradeSetup $setup, Collection $signals): ?TradeSetup {

                /** @var Signal $signal */
                $signal = $signals->last();
                $info = (object)$signal->info;

                $targetLevels = Fib::targetLevels($info->prices, $info->nearest['level'], $isBuy = $setup->isBuy());

                $firstTarget = $targetLevels[0];
                $firstTargetPrice = $info->prices[$firstTarget];
                $timestamp = $setup->timestamp;
                /** @var Fib $info */
                $fib = $this->indicator($signal);

                if ($fib->isBindable($firstTarget))
                {
                    $fib->bind($setup, 'close_price', $firstTarget, timestamp: $timestamp);
                }
                else
                {
                    $setup->close_price = $firstTargetPrice;
                }

                $targetRoi = Calc::roi($isBuy, $entryPrice = $setup->price, $firstTargetPrice);
                $reward = $targetRoi / 100;
                $risk = $reward / 2;
                $setup->size = 100;

                $fib->bind($setup, 'price', $info->nearest['level'], timestamp: $timestamp);
                $fib->bind($setup, 'stop_price', $info->nearest['level'],
                           $isBuy
                               ? static fn(float $price): float => $price - $price * $risk
                               : static fn(float $price): float => $price + $price * $risk,
                           $timestamp);
                $this->newAction($setup, MoveStop::class, [
                    'target'         => ['roi' => $targetRoi / 2],
                    'new_stop_price' => $entryPrice
                ]);
                return $setup;
            }
        ];
    }
}