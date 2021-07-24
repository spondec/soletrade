<?php

use App\Trade\Exchange\Spot\Binance;
use App\Trade\Scanner;

class ScannerTest extends \Tests\TestCase
{
    public function test_scan()
    {
        $scanner = Binance::instance()->scanner();
        $scanner->setSymbolFilterer(fn($symbols) => array_slice($symbols, 0, 2));
//        $res = $scanner->scan('1h');
//        $res = $scanner->scan('15m');
//        $res = $scanner->scan('5m');
//        $res = $scanner->scan('30m');
//        $res = $scanner->scan('4h');
//        $res = $scanner->scan('1d');
        $res = $scanner->scan('1d');

        $this->assertInstanceOf(\App\Models\Candles::class, $res->first());
    }
}