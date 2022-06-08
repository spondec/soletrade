<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Trade\Action\Handler;
use App\Trade\ChangeLog;
use App\Trade\Contract\Binding\Bindable;
use App\Trade\HasInstanceEvents;
use JetBrains\PhpStorm\Pure;

class Price
{
    use HasInstanceEvents;

    protected array $events = ['changed'];
    protected bool $isLocked = false;

    protected ?string $lockedBy;

    protected static array $modifiers = [
        Price::class,
        Handler::class,
        Position::class,
        Bindable::class,
        TradeLoop::class,
    ];
    protected ChangeLog $log;

    public function __construct(protected float $price,
                                int $timestamp)
    {
        $this->log = new ChangeLog($this->price, $timestamp);
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    protected function getCaller(): string
    {
        return \debug_backtrace()[2]['class'];
    }

    public function unlock(): void
    {
        if ($this->lockedBy !== $this->getCaller()) {
            throw new \LogicException('Unlocking class must be the same as locking class.');
        }
        $this->isLocked = false;
        $this->lockedBy = null;
    }

    public function lock(): void
    {
        $this->assertUnlocked();
        $this->assertModifier($locker = $this->getCaller());
        $this->lockedBy = $locker;
        $this->isLocked = true;
    }

    public function get(): float
    {
        return $this->price;
    }

    public function set(float $price, int $timestamp, string $reason, bool $force = false): void
    {
        if (!$force) {
            $this->assertUnlocked();
        }

        if ($price == $this->price) {
            return;
        }

        $this->fireEvent('changed', [
            'from' => $this->price,
            'to' => $price,
        ]);

        $this->price = $price;
        $this->newLog($timestamp, $reason, $force);
    }

    public function newLog(int $timestamp, string $reason, bool $force = false): void
    {
        $this->log->new($this->price, $timestamp, $force ? "[FORCED] $reason" : $reason);
    }

    protected function assertUnlocked(): void
    {
        if ($this->isLocked) {
            throw new \LogicException('Attempted to set a locked price.');
        }
    }

    #[Pure]
 public function log(): ChangeLog
 {
     return $this->log;
 }

    protected function assertModifier(string $modifier): void
    {
        foreach (static::$modifiers as $class) {
            if ($modifier === $class || \is_subclass_of($modifier, $class)) {
                return;
            }
        }

        throw new \InvalidArgumentException($modifier.' is not allowed as a price modifier.');
    }
}
