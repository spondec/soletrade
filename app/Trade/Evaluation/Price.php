<?php

namespace App\Trade\Evaluation;

use App\Trade\Strategy\TradeAction\AbstractTradeActionHandler;

class Price
{
    protected bool $isLocked = false;

    protected ?object $lockedBy;

    protected static array $modifiers = [
        AbstractTradeActionHandler::class,
        TradeLoop::class,
        Position::class
    ];
    protected array $history = [];

    public function __construct(protected float     $price,
                                protected ?\Closure $onChange = null)
    {
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function unlock(object $unlockedBy): void
    {
        if ($this->lockedBy !== $unlockedBy)
        {
            throw new \InvalidArgumentException('Unlocker must be the same as locker.');
        }
        $this->isLocked = false;
        $this->lockedBy = null;
    }

    public function lock(object $lockedBy): void
    {
        $this->assertUnlocked();
        $this->assertModifier($lockedBy);
        $this->lockedBy = $lockedBy;
        $this->isLocked = true;
    }

    public function get(): float
    {
        return $this->price;
    }

    public function set(float $price, string $reason, bool $force = false): void
    {
        if (!$force)
        {
            $this->assertUnlocked();
        }

        if ($price === $this->price)
        {
            return;
        }

        if ($this->onChange)
        {
            ($this->onChange)(price: $this, from: $this->price, to: $price);
        }

        $this->history[] = [
            'from'   => $this->price,
            'to'     => $price,
            'reason' => $force ? 'FORCED: ' . $reason : $reason
        ];
        $this->price = $price;
    }

    protected function assertUnlocked(): void
    {
        if ($this->isLocked)
        {
            throw new \LogicException("Attempted to set a locked price.");
        }
    }

    public function history(): array
    {
        return $this->history;
    }

    protected function assertModifier(object $modifier): void
    {
        foreach (static::$modifiers as $class)
        {
            if ($modifier instanceof $class)
            {
                return;
            }
        }

        throw new \InvalidArgumentException($modifier::class . ' is not allowed as a price modifier.');
    }
}