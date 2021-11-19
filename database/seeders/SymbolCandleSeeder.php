<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SymbolCandleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ini_set('memory_limit', -1);

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0;');
        DB::unprepared(file_get_contents('database/exchanges_symbols_candles.sql'));
        DB::unprepared('SET FOREIGN_KEY_CHECKS=1;');
    }
}
