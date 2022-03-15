<?php

namespace App\Trade;

/**
 * @property string[] events
 */
trait HasInstanceEvents
{
    /** @var array<string,array<\Closure>> */
    private array $listeners = [];

    /**
     * @var array<string,string>
     */
    private array $bypassed = [];

    public function listen(string $eventName, \Closure $onEvent = null): void
    {
        $this->assertEventExists($eventName);
        $this->listeners[$eventName][] = $onEvent;
    }

    protected function fireEvent(string $eventName, ...$params): void
    {
        $this->assertEventExists($eventName);

        if (isset($this->bypassed[$eventName]))
        {
            unset($this->bypassed[$eventName]);
            return;
        }

        foreach ($this->listeners[$eventName] ?? [] as $onEvent)
        {
            $onEvent($this, ...$params);
        }
    }

    public function bypassEventOnce(string $eventName): void
    {
        $this->assertEventExists($eventName);
        $this->bypassed[$eventName] = true;
    }

    protected function assertEventExists(string $eventName): void
    {
        if (!\in_array($eventName, $this->events))
        {
            throw new \InvalidArgumentException("Event '$eventName' doesn't exist.");
        }
    }
}