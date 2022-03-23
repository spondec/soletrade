<?php

namespace Trade\Evaluation;

use App\Trade\Evaluation\Price;
use PHPUnit\Framework\TestCase;

class PriceTest extends TestCase
{
    public function test_set()
    {
        $price = new Price(101, time());
        $price->set(100, time(), 'Change', false);

        $this->assertEquals(100, $price->get());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Attempted to set a locked price');

        \Closure::bind(function () use ($price) {
            $price->lock();
            $price->set(100, time(), 'Change', false);
        }, $price, Price::class)();
    }

    public function test_lock_unlock()
    {
        $price = new Price(123, time());

        $test = $this;
        \Closure::bind(function () use ($price, $test) {
            $test->assertFalse($price->isLocked());
            $price->lock();
            $test->assertTrue($price->isLocked());
            $price->unlock();
            $test->assertFalse($price->isLocked());
        }, $price, Price::class)();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not allowed as a price modifier');
        $price->lock();
    }

    public function test_new_change_log()
    {
        $price = new Price(123, time());
        $price->set($newPrice = 100, $changeTime = time(), $reason = 'Change');

        $logEntry = $price->log()->get()[1];

        $this->assertEquals($newPrice, $logEntry['value']);
        $this->assertEquals($reason, $logEntry['reason']);
        $this->assertEquals($changeTime, $logEntry['timestamp']);
    }
}
