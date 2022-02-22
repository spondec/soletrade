<?php

namespace Trade;

use App\Trade\HasEvents;
use PHPUnit\Framework\TestCase;

class HasEventsTest extends TestCase
{
    public function test_listen()
    {
        $hasEvents = new class {
            use HasEvents;

            protected array $events = ['test'];

            public function fire()
            {
                $this->fireEvent('test');
            }
        };

        $hasEvents->listen('test', function () {
            $this->assertTrue(true);
        });

        $hasEvents->fire();
    }
}
