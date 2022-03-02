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

        $this->expectNotToPerformAssertions();
        $hasEvents->bypass();
        $hasEvents->fire();
    }
}
