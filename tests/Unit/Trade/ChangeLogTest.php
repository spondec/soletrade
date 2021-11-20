<?php

namespace Trade;

use App\Trade\ChangeLog;
use PHPUnit\Framework\TestCase;

class ChangeLogTest extends TestCase
{
    public function test_new()
    {
        $changeLog = new ChangeLog(100);
        $this->assertEquals([
            [
                'value'     => 100,
                'timestamp' => 0,
                'reason'    => ''
            ]
        ], $changeLog->get());
        $changeLog->new(200, $time = time(), 'change');
        $this->assertEquals([
            [
                'value'     => 100,
                'timestamp' => 0,
                'reason'    => ''
            ],
            ['value'     => 200,
             'timestamp' => $time,
             'reason'    => 'change'
            ]
        ], $changeLog->get());
    }

    public function test_new_change_date_is_lower_than_the_last_change_date_throws_exception()
    {
        $changeLog = new ChangeLog(100);
        $changeLog->new(100, $time = time(), '');
        $this->expectExceptionMessage('New change date must be greater than last change date');
        $changeLog->new(200, $time - 1000, '');
    }
}
