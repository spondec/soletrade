<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Calc;
use App\Trade\Indicator\Fib;
use App\Trade\Strategy\TradeAction\MoveStop;
use Illuminate\Support\Collection;

class FibScalp extends AbstractStrategy
{
    protected function indicatorSetup(): array
    {
        return [
            Fib::class => [
                'config' => [
                    'period'              => 144,
                    'distanceToLevel'     => 1,
                    'totalBarsAfterLevel' => 3,
                    'levels'              => [236, 382, 500, 618, 786, 886, 114],
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
                            $signal->info = array_merge($fib, ['prices' => $value]);
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

                    $targetLevels = Fib::targetLevels($fib['prices'], $fib['level'], $isBuy = $setup->isBuy());

                    $firstTarget = $targetLevels[0];
                    $targetPrice = $fib['prices'][$firstTarget];

                    /** @var Fib $fib */
                    $fib = $this->indicator($signal->indicator_id);
                    $fib->bind($setup, 'close_price', $firstTarget, timestamp: $setup->timestamp);
                    $this->bind($setup, 'price', 'last_signal_price');

                    $targetRoi = Calc::roi($isBuy, $entryPrice = $setup->price, $targetPrice);
                    $reward = $targetRoi / 100;
                    $risk = $reward / 3;
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