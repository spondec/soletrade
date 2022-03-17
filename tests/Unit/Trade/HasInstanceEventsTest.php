<?php

namespace Trade;

use App\Trade\HasInstanceEvents;
use PHPUnit\Framework\TestCase;

class HasInstanceEventsTest extends TestCase
{
    public function test_listen()
    {
        $hasEvents = $this->getHasEventsObject();

        $hasEvents->listen('test', function () {
            $this->assertTrue(true);
        });

        $hasEvents->fire();
    }

    protected function getHasEventsObject(): object
    {
        return new class {
            use HasInstanceEvents;

            protected array $events = ['test'];
            protected array $eventTriggers = [
                'event' => 'test',
            ];

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

    public function test_bypass_event_once()
    {
        $hasEvents = $this->getHasEventsObject();

        $hasEvents->listen('test', function () {
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

        $hasEvents->listen('test', function () {
            $this->assertTrue(true);
        });

        $hasEvents->listen('event', function () {
            $this->assertTrue(true);
        });

        $hasEvents->fire();

        $this->assertEquals(2, $this->getCount());
    }
}
