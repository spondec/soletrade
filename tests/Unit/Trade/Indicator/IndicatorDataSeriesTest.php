<?php

namespace Trade\Indicator;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\IndicatorDataSeries;
use App\Trade\Indicator\IndicatorSeriesState;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class IndicatorDataSeriesTest extends TestCase
{
    public function testGet(): void
    {
        $series = $this->getSeries($state);

        $state->index = 4;
        $this->assertEquals(5, $series->get());
        $this->assertEquals(4, $series->get(1));
        $this->assertEquals(3, $series->get(2));
        $this->assertEquals(2, $series->get(3));
        $this->assertEquals(1, $series->get(4));
        $this->assertEquals(null, $series->get(5));
    }

    public function test__clone(): void
    {
        $series = $this->getSeries($state);

        $state->index = 4;

        $this->assertEquals($series, $series->value());

        $clone = $series->value(2);
        $this->assertNotEquals($clone, $series);
        $this->assertNotEquals(5, $clone->get());
    }

    public function testCandle(): void
    {
        $series = $this->getSeries($state);
        $this->assertEquals(11, $series->candle()->t);
        $this->assertNull($series->value(5)->candle());
    }

    public function testValue(): void
    {
        $series = $this->getSeries($state, 'foo');
        $this->assertIsArray($series->get());
        $this->assertEquals(1, $series->value(column: 'foo')->get());


        $this->assertNull($series->value(column: 'bar')->get());
    }

    protected function getSeries(IndicatorSeriesState &$state = null, ?string $withColumn = null): IndicatorDataSeries
    {
        $state = new IndicatorSeriesState();

        if ($withColumn)
        {
            $data = [
                11 => [$withColumn => 1],
                12 => [$withColumn => 2],
                13 => [$withColumn => 3],
                14 => [$withColumn => 4],
                15 => [$withColumn => 5],
            ];
        }
        else
        {
            $data = [
                11 => 1,
                12 => 2,
                13 => 3,
                14 => 4,
                15 => 5,
            ];
        }

        $candles = new CandleCollection([
            (object)[
                't' => 11
            ],
            (object)[
                't' => 12,
            ],
            (object)[
                't' => 13,
            ],
            (object)[
                't' => 14,
            ],
            (object)[
                't' => 15,
            ],
        ]);

        return new IndicatorDataSeries($state, new Collection($data), $candles);
    }
}
