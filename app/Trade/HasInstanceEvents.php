<?php

namespace App\Trade;

/**
 * @property string[] events
 */
trait HasInstanceEvents
{
    /** @var array<string,array<\Closure>> */
    protected array $listeners = [];

    /**
     * @var array<string,string>
     */
    protected array $bypassed = [];

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

        if (isset($this->bypassed[$eventName]))
        {
            unset($this->bypassed[$eventName]);
            return;
        }

        $this->runListeners($eventName);
    }

    private function runListeners(string $eventName): void
    {
        foreach ($this->listeners[$eventName] ?? [] as $onEvent)
        {
            $onEvent($this);
        }
    }

    protected function bypassEventOnce(string $eventName)
    {
        $this->bypassed[$eventName] = true;
    }
}