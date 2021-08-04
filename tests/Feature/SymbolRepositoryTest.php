<?php

namespace Tests\Feature;

use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\Exchange\Spot\Binance;
use App\Trade\Indicator\RSI;
use Tests\TestCase;

class SymbolRepositoryTest extends TestCase
{
    public function test_get_candles()
    {
       $repo = new SymbolRepository;

       $res = $repo->candles(Binance::instance(),
           'BCC/BTC',
           '1d',
           1542672000000,
       1000,
       [RSI::class]);

       dump($res->indicator('RSI'));

       $this->assertInstanceOf(Symbol::class, $res);
    }
}
