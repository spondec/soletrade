<?php

namespace Trade;

use App\Trade\HasInstanceEvents;
use PHPUnit\Framework\TestCase;

class HasInstanceEventsTest extends TestCase
{
    public function test_listen()
    {
        $hasEvents = $this->getHasEventsObject();

        $hasEvents->listen('event1', function () {
            $this->assertTrue(true);
        });

        $hasEvents->fire();
    }

    protected function getHasEventsObject(): object
    {
        return new class {
            use HasInstanceEvents;

            protected array $events = ['event1', 'event2'];
            protected array $eventTriggers = [
                'event3' => ['event1', 'event2'],
            ];

            public function fire()
            {
                $this->fireEvent('event1');
            }

            public function bypass()
            {
                $this->bypassEventOnce('event1');
            }
        };
    }

    public function test_bypass_event_once()
    {
        $hasEvents = $this->getHasEventsObject();

        $hasEvents->listen('event1', function () {
            $this->assertTrue(true);
        });

        $hasEvents->bypass();
        $hasEvents->fire();
        $hasEvents->fire();

        $this->assertEquals(1, $this->getCount());
    }

    public function test_triggers()
    {
        $hasEvents = $this->getHasEventsObject();

        $hasEvents->listen('event2', function () {
            $this->assertTrue(true);
        });

        $hasEvents->listen('event3', function () {
            $this->assertTrue(true);
        });

        $hasEvents->fire();

        $this->assertEquals(2, $this->getCount());
    }
}
