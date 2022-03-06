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
        $this->listeners[$eventName][] = $onEvent;
    }

    protected function fireEvent(string $eventName, ...$params): void
    {
        if (!\in_array($eventName, $this->events))
        {
            throw new \InvalidArgumentException("Event '$eventName' doesn't exist.");
        }

        if (isset($this->bypassed[$eventName]))
        {
            unset($this->bypassed[$eventName]);
            return;
        }

        foreach ($this->listeners[$eventName] ?? [] as $onEvent)
        {
            $onEvent($this, $params);
        }
    }

    protected function bypassEventOnce(string $eventName): void
    {
        $this->bypassed[$eventName] = true;
    }
}