<?php

namespace Tests\Unit\Trade\Process;

use App\Trade\Process\Recoverable;
use PHPUnit\Framework\TestCase;

class RecoverableTest extends TestCase
{
    public function test_empty_handle_throws_exception()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No Throwable');
        new Recoverable(fn () => $this->assertTrue(true), 1, 1);
    }

    public function test_retry_in_seconds()
    {
        $recoverable = new Recoverable(function () {
            static $time;

            if (!$time) {
                $time = time();
                throw new \Exception('Failed');
            }

            $this->assertEquals(time(), $time + 1);
        }, 1, 1, [\Exception::class]);

        $recoverable->run();
    }

    public function test_retry_limit()
    {
        $recoverable = new Recoverable(function () {
            static $count;

            ++$count;

            if ($count < 2) {
                throw new \Exception('Failed');
            }

            $this->assertEquals(2, $count);
        }, 1, 2, [\Exception::class]);

        $recoverable->run();
    }

    public function test_unhandled_exception_gets_thrown()
    {
        $this->expectError();
        $recoverable = new Recoverable(function () {
            throw new \Error('Failed');
        }, 1, 1, [\Exception::class]);

        $recoverable->run();
    }

    public function test_subclass_throwable()
    {
        $recoverable = new Recoverable(function () {
            static $count;
            if (!$count) {
                $count = 1;
                throw new \LogicException('Failed');
            }
            $this->assertEquals(1, $count);
        }, 1, 1, [\Exception::class]);

        $recoverable->run();
    }

    public function test_negative_retry_limit()
    {
        try {
            $count = 0;
            (new Recoverable(function () use (&$count) {
                ++$count;

                throw new \Exception('To be caught');
            }, 1, 2, [\Exception::class]))->run();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
            $this->assertEquals('To be caught', $e->getMessage());
            $this->assertEquals(3, $count);
        }
    }
}
