<?php

namespace Trade;

use App\Trade\HasEvents;
use PHPUnit\Framework\TestCase;

class HasEventsTest extends TestCase
{
    public function test_listen()
    {
        $hasEvents = $this->getHasEventsObject();

        $hasEvents->listen('test', function () {
            $this->assertTrue(true);
        });

        $hasEvents->fire();
    }

    public function test_bypass_event_once()
    {

        $hasEvents = $this->getHasEventsObject();

        $hasEvents->listen('test', function () {
            $this->assertTrue(true);
        });

        $this->expectNotToPerformAssertions();
        $hasEvents->bypass();
        $hasEvents->fire();
    }

    protected function getHasEventsObject(): object
    {
        return new class {
            use HasEvents;

            protected array $events = ['test'];

            public function fire()
            {
                $this->fireEvent('test');
            }

            public function bypass()
            {
                $this->bypassEventOnce('test');
            }
        };
    }
}
