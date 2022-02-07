<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class SymbolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $exchanges = Config::get('trade.exchanges');
        foreach ($exchanges as $exchange)
        {
            $exchange['class']::instance()
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
}
