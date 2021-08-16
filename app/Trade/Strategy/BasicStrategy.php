<?php

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Indicator\MACD;
use App\Trade\Indicator\RSI;

class BasicStrategy extends AbstractStrategy
{
    const RSI_BELOW_30 = 'RSI-BELOW_30';
    const RSI_ABOVE_70 = 'RSI-ABOVE_70';

    const MACD_CROSSOVER = 'MACD-CROSSOVER';
    const MACD_CROSSUNDER = 'MACD-CROSSUNDER';

    protected function indicatorSetup(): array
    {
        return [
            RSI::class => [
                'config' => [],
                'signal' => function (Signal $signal, RSI $indicator, int $timestamp, int $value): ?Signal {

                    if ($value <= 30)
                    {
                        $signal->name = static::RSI_BELOW_30;
                        $signal->side = Signal::BUY;
                    }
                    else if ($value >= 70)
                    {
                        $signal->name = static::RSI_ABOVE_70;
                        $signal->side = Signal::SELL;
                    }
                    else
                    {
                        return null;
                    }

                    $signal->price = $indicator->closePrice();

                    return $signal;
                }
            ],

            MACD::class => [
                'config' => [],
                'signal' => function (Signal $signal, MACD $indicator, int $timestamp, array $value) {
                    if ($indicator->crossOver(fn($v): float => $v['m'], fn($v): float => $v['s']))
                    {
                        $signal->name = static::MACD_CROSSOVER;
                        $signal->side = Signal::BUY;
                    }
                    else if ($indicator->crossUnder(fn($v): float => $v['m'], fn($v): float => $v['s']))
                    {
                        $signal->name = static::MACD_CROSSUNDER;
                        $signal->side = Signal::SELL;
                    }
                    else
                    {
                        return null;
                    }

                    $signal->price = $indicator->closePrice();

                    return $signal;
                }
            ]
        ];
    }

    protected function tradeSetup(): array
    {
        return [
            [
                'signals' => [RSI::class]
            ],
            [
                'signals' => [RSI::class, MACD::class]
            ]
        ];
    }
}