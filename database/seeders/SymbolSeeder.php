<?php

namespace Database\Seeders;

use App\Trade\Exchange\Spot\Binance;
use Illuminate\Database\Seeder;

class SymbolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Binance::instance()
            ->updater()
            ->bulkIndexSymbols([
                '1m',
                '5m',
                '15m',
                '30m',
                '1h',
                '4h',
                '1d',
                '1w',
                '1M'
            ]);
    }
}
