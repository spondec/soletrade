<?php

namespace Indicators;

use App\Indicators\Combined;
use App\Indicators\EMA;
use App\Indicators\SMA;
use Tests\Feature\Indicators\IndicatorTestCase;

class CombinedTest extends IndicatorTestCase
{
    public function test_combined()
    {
        $symbol = $this->createCandles(100);
        $combined = new Combined($symbol, $symbol->candles(100), [
            'indicators.sma_8'  => [
                'alias'  => 'sma_8',
                'class'  => SMA::class,
                'config' => ['timePeriod' => 8]
            ],
            'indicators.ema_13' => [
                'alias'  => 'ema_13',
                'class'  => EMA::class,
                'config' => ['timePeriod' => 13]
            ]
        ]);

        $sma = new SMA($symbol, $symbol->candles(100), ['timePeriod' => 8]);
        $ema = new EMA($symbol, $symbol->candles(100), ['timePeriod' => 13]);

        $data = $combined->data();

        $this->assertEquals($ema->data()->all(),
            array_combine(
                $data->keys()->slice(-88, 88)->all(),
                $data->pluck('ema_13')->filter()->all()
            )
        );

        $this->assertEquals($sma->data()->all(),
            array_combine(
                $data->keys()->slice(0, 93)->all(),
                $data->pluck('sma_8')->all()
            )
        );

        $this->assertIsFloat($data->first()['sma_8']);

        $this->assertArrayNotHasKey('ema_13', $data->first());
        $this->assertIsFloat($data->values()[5]['ema_13']);

        $this->assertIsFloat($data->last()['sma_8']);
        $this->assertIsFloat($data->last()['ema_13']);

        $this->assertCount(100 - 7, $data);
    }
}
