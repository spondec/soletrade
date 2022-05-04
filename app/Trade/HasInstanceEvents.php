<?php

declare(strict_types=1);

namespace App\Trade;

use App\Trade\Helper\ClosureHash;

/**
 *
 * @property string[]                           events
 *
 * Events triggered by another set of events.
 * @property array<string,array<string, array>> eventTriggers
 */
trait HasInstanceEvents
{
    /** @var array<string,array<\Closure>> */
    private array $listeners = [];

    /**
     * @var array<string,string>
     */
    private array $bypassed = [];

    private array $listenerHash = [];

    public function listen(string $eventName, \Closure $onEvent): void
    {
        $this->assertEventExists($eventName);

        if (\in_array($hash = ClosureHash::from($onEvent), $this->listenerHash[$eventName] ?? []))
        {
            throw new \LogicException("Listener already registered.");
        }

        $this->listenerHash[$eventName][] = $hash;
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

        $this->handleTriggers($eventName, $params);
    }

    public function bypassEventOnce(string $eventName): void
    {
        $this->assertEventExists($eventName);
        $this->bypassed[$eventName] = true;
    }

    private function assertEventExists(string $eventName): void
    {
        if (!\in_array($eventName, $this->events) && !isset($this->eventTriggers[$eventName]))
        {
            throw new \InvalidArgumentException("Event '$eventName' doesn't exist.");
        }
    }

    private function handleTriggers(string $triggerEventName, array $params): void
    {
        if (!isset($this->eventTriggers))
        {
            return;
        }

        foreach ($this->eventTriggers as $event => $triggers)
        {
            $triggers = \is_string($triggers) ? [$triggers] : $triggers;
            if (\in_array($triggerEventName, $triggers))
            {
                $this->fireEvent($event, ...$params);
            }
        }
    }
}