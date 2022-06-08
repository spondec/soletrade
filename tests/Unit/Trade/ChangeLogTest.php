<?php

namespace Tests\Unit\Trade;

use App\Trade\ChangeLog;
use PHPUnit\Framework\TestCase;

class ChangeLogTest extends TestCase
{
    public function test_new()
    {
        $first = [
            'value'     => 100,
            'timestamp' => $time = time() * 1000,
            'reason'    => 'Created',
        ];
        $next = [
            'value'     => 200,
            'timestamp' => $time + 1000,
            'reason'    => 'change',
        ];

        $changeLog = new ChangeLog(...$first);

        $this->assertEquals([$first], $changeLog->get());
        $changeLog->new(...$next);
        $this->assertEquals([$first, $next], $changeLog->get());
    }

    public function test_new_change_date_is_lower_than_the_last_change_date_throws_exception()
    {
        $changeLog = new ChangeLog(100, $time = time() * 1000, 'Created');
        $this->expectExceptionMessage('New change date must be greater than last change date');
        $changeLog->new(200, $time - 1000, '');
    }
}
