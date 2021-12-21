<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Trade\ChangeLog;
use App\Trade\Strategy\TradeAction\AbstractTradeActionHandler;
use JetBrains\PhpStorm\Pure;

class Price
{
    protected bool $isLocked = false;

    protected ?object $lockedBy;

    protected static array $modifiers = [
        AbstractTradeActionHandler::class,
        TradeLoop::class,
        Position::class
    ];
    protected ChangeLog $log;

    public function __construct(protected float     $price,
                                protected ?\Closure $onChange = null)
    {
        $this->log = new ChangeLog($this->price);
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

    public function set(float $price, int $timestamp, string $reason, bool $force = false): void
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

        $this->price = $price;
        $this->log($timestamp, $reason, $force);
    }

    public function log(int $timestamp, string $reason, bool $force = false): void
    {
        $this->log->new($this->price, $timestamp, $force ? "FORCED: {$reason}" : $reason);
    }

    protected function assertUnlocked(): void
    {
        if ($this->isLocked)
        {
            throw new \LogicException("Attempted to set a locked price.");
        }
    }

    #[Pure] public function history(): array
    {
        return $this->log->get();
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