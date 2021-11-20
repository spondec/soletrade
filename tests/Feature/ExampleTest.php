<?php

namespace Tests\Feature;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function inttest(int $test)
    {

    }

    public function test_bug_case()
    {
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {

        $repo = new SymbolRepository();

//        Binance::instance()->updater()->updateByInterval('1m', 1);

        /** @var Symbol $symbol */
        $symbol = Symbol::query()
            ->where('symbol', 'BTC/USDT')
            ->where('interval', '1m')
            ->first();

        $repo->fetchSymbolFromExchange($symbol->exchange(), $symbol->symbol, $symbol->interval);
        $symbol->exchange()->updater()->update($symbol);
    }
}

