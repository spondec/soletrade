<?php

namespace App\Trade;

/**
 * @property string[] events
 */
trait HasEvents
{
    /** @var array<array<\Closure>> */
    protected array $listeners = [];

    public function listen(string $eventName, \Closure $onEvent = null)
    {
        $this->listeners[$eventName][] = $onEvent;
    }

    protected function fireEvent(string $eventName): void
    {
        if (!\in_array($eventName, $this->events))
        {
            throw new \InvalidArgumentException("Event '$eventName' doesn't exist.");
        }

        foreach ($this->listeners[$eventName] ?? [] as $onEvent)
        {
            $onEvent($this);
        }
    }
}