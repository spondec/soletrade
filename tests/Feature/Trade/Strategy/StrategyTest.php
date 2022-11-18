<?php

namespace Tests\Feature\Trade\Strategy;

use App\Models\Symbol;
use Database\Seeders\SymbolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyTest extends TestCase
{
    use RefreshDatabase;

    public function assertKeysMatch(array $expected, array $actual, array $keys): void
    {
        $assertEquals = function (string $key, array $expected, array $actual) {
            $this->assertEquals($expected[$key], $actual[$key]);
        };

        foreach ($keys as $key) {
            $assertEquals($key, $expected, $actual);
        }
    }

    protected function tearDown(): void
    {

    }


    public function testExampleStrategy()
    {
        Symbol::insert([
            'symbol' => 'BTC/USDT',
            'interval' => '1d',
            'exchange_id' => 1,
        ]);

        $fixture = json_decode(
            json: file_get_contents(__DIR__ . '/fixtures/example-strategy-btcusdt-1d-binance-01-01-2017~01-01-2022.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );

        $summary = $fixture['strategy']['trades']['summary'];
        $indicators = $fixture['indicators'];
        $evaluations = $fixture['strategy']['trades']['evaluations'];

        //01-01-2017 ~ 01-01-2022
        //BTC/USDT 1d
        $uri = 'api/chart?strategy=ExampleStrategy&symbol=BTC%2FUSDT&exchange=Binance&interval=1d&indicatorConfig=%7B%7D&limit=1000&range=%7B%22start%22:%222017-01-01T00:00:00.000Z%22,%22end%22:%222022-01-01T23:59:59.000Z%22%7D';

        $response = $this->get($uri);
        $response->assertOk();

        $summaryResponse = $response->json('strategy.trades.summary');
        $indicatorResponse = $response->json('indicators');
        $evaluationsResponse = $response->json('strategy.trades.evaluations');

        $this->assertEquals($summary, $summaryResponse);
        $this->assertEquals($indicators, $indicatorResponse);

        $keys = [
            'relative_roi',
            'highest_roi',
            'lowest_roi',
            'used_size',
            'entry_price',
            'avg_entry_price',
            'exit_price',
            'target_price',
            'target_price',
            'stop_price',
            'highest_price',
            'lowest_price',
            'highest_entry_price',
            'lowest_entry_price',
            'is_entry_price_valid',
            'is_ambiguous',
            'is_stopped',
            'is_closed',
            'entry_timestamp',
            'exit_timestamp',
        ];

        foreach ($evaluationsResponse as $key => $evalResponse) {
            $this->assertKeysMatch($evaluations[$key], $evalResponse, $keys);
        }
    }
}