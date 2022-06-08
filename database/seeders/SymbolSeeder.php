<?php

namespace Database\Seeders;

use App\Trade\Exchange\Exchange;
use App\Trade\Repository\ConfigRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class SymbolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /** @var ConfigRepository $repo */
        $repo = App::make(ConfigRepository::class);

        /** @var Exchange|string $exchange */
        foreach ($repo->exchanges as $exchange) {
            $exchange::instance()
                ->update()
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
