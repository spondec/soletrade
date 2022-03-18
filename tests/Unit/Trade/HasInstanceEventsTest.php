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

        $hasEvents->fire('event1');
    }

    protected function getHasEventsObject(): object
    {
        return new class {
            use HasInstanceEvents;

            protected array $events = ['event1', 'event2'];
            protected array $eventTriggers = [
                'event3' => ['event1', 'event2'],
            ];

            public function fire(string $eventName)
            {
                $this->fireEvent($eventName);
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
        $hasEvents->fire('event1');
        $hasEvents->fire('event1');

        $this->assertEquals(1, $this->getCount());
    }

    public function test_triggers()
    {
        $hasEvents = $this->getHasEventsObject();

        $triggerEventCounter = 0;
        $triggeredEventCounter = 0;

        $triggerEvents = ['event1', 'event2'];

        foreach ($triggerEvents as $triggerEvent)
        {
            $hasEvents->listen($triggerEvent, function () use (&$triggerEventCounter) {
                $triggerEventCounter++;
            });
        }

        $hasEvents->listen('event3', function () use (&$triggeredEventCounter) {
            $triggeredEventCounter++;
        });

        foreach ($triggerEvents as $triggerEvent)
        {
            $hasEvents->fire($triggerEvent);
        }

        $this->assertEquals(2, $triggeredEventCounter);
        $this->assertEquals(2, $triggeredEventCounter);
    }
}
