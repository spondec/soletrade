<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Trade\Calc;
use App\Trade\ChangeLog;
use App\Trade\HasInstanceEvents;
use App\Trade\Side;

class Position
{
    use HasInstanceEvents;

    protected array $events = ['close', 'stop'];

    protected array $eventTriggers = ['exit' => ['close', 'stop']];

    public const MAX_SIZE = 100;

    protected bool $isStopped = false;
    protected bool $isClosed = false;

    protected float $remainingSize;

    protected ?int $exitTime = null;

    protected ChangeLog $transactionLog;

    protected float $multiplier = 1;
    protected float $amount = 0;

    protected float $pnl = 0;
    protected readonly float $roi;
    protected readonly float $relativeRoi;

    protected float $maxUsedSize = 0;

    protected float $exitPrice;

    public function __construct(public           readonly Side $side,
                                protected float  $size,
                                protected int    $entryTime,
                                protected Price  $entry,
                                protected ?Price $exit,
                                protected ?Price $stop)
    {
        $this->remainingSize = Position::MAX_SIZE;
        $this->assertSize($this->size);
        $this->transactionLog = new ChangeLog([
            'increase' => true,
            'price'    => $this->entry->get(),
            'size'     => $this->size
        ], $this->entryTime, 'Position entry');

        $this->enter();
    }

    protected function assertSize(float $size): void
    {
        if ($size > Position::MAX_SIZE)
        {
            throw new \InvalidArgumentException('Maximum position size is limited to ' . Position::MAX_SIZE);
        }
    }

    protected function newTransaction(bool $increase, float $price, float $size, int $timestamp, string $reason): void
    {
        if ($increase)
        {
            $this->assertNotGreaterThanRemaining($size);
            $this->maxUsedSize += $size;
            $this->remainingSize -= $size;
            $this->amount += $size / $price;
        }
        else
        {
            $this->assertNotGreaterThanUsedSize($size);

            $reduce = $this->amount / $this->getUsedSize() * $size;
            $pnl = $reduce * $price - $reduce * $this->getBreakEvenPrice();
            if (!$this->isBuy())
            {
                $pnl *= -1;
            }
            $this->pnl += $pnl;
            $this->amount -= $reduce;
            $this->remainingSize += $size;
        }

        $this->transactionLog->new([
            'increase' => $increase,
            'price'    => $price,
            'size'     => $size,
        ], $timestamp, $reason);

        if (!$this->amount && $this->isOpen())
        {
            throw new \LogicException('Position is open but no asset left. Exit trades must be performed with stop() or close().');
        }
    }

    protected function assertNotGreaterThanRemaining(float $size): void
    {
        if ($size > $this->remainingSize)
        {
            throw new \LogicException('The requested size is bigger than the remaining size.');
        }
    }

    public function getUsedSize(): float
    {
        return Position::MAX_SIZE - $this->remainingSize;
    }

    public function getBreakEvenPrice(): float
    {
        $usedSize = $this->getUsedSize();
        $totalSize = $usedSize + $this->pnl;

        $breakEvenRoi = ($usedSize - $totalSize) * 100 / $usedSize;
        $breakEvenPrice = $usedSize / $this->amount;

        $differ = ($breakEvenPrice * $breakEvenRoi / 100);

        if ($this->isBuy())
        {
            return $breakEvenPrice + $differ;
        }
        return $breakEvenPrice - $differ;
    }

    public function isOpen(): bool
    {
        return !$this->isClosed && !$this->isStopped;
    }

    public function getAssetAmount(): float
    {
        return $this->amount;
    }

    public function addStopPrice(Price $price): void
    {
        if ($this->stop)
        {
            throw new \LogicException('Stop price is already set.');
        }

        $this->stop = $price;
    }

    public function addExitPrice(Price $price): void
    {
        if ($this->exit)
        {
            throw new \LogicException('Exit price is already set.');
        }

        $this->exit = $price;
    }

    public function getExitPrice(): float
    {
        return $this->exitPrice;
    }

    public function setMultiplier(float $multiplier): void
    {
        $this->multiplier = $multiplier;
    }

    public function exitTime(): ?int
    {
        return $this->exitTime;
    }

    public function isStopped(): bool
    {
        return $this->isStopped;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function close(int $exitTime): void //TODO:: rename to exit
    {
        if ($this->isClosed)
        {
            throw new \LogicException('Attempted to close an already closed position.');
        }

        $exitTime = Calc::asMs($exitTime);

        $this->lockIfUnlocked($this->exit);
        $this->exitPrice = $this->exit->get();

        $this->saveRoi($this->exitPrice);

        $this->isClosed = true;
        $this->exitTime = $exitTime;

        $this->insertExitTransaction($exitTime);

        $this->fireEvent('close');
    }

    protected function lockIfUnlocked(Price $price): void
    {
        if (!$price->isLocked())
        {
            $price->lock();
        }
    }

    protected function saveRoi(float $price): void
    {
        $this->roi = $this->roi($price);
        $this->relativeRoi = $this->relativeRoi($price);
    }

    public function roi(float $lastPrice): float
    {
        $this->assertCanRecalculateRoi();

        if ($this->isBuy())
        {
            return $this->calcLongRoi($lastPrice, $this->getUsedSize(), $this->maxUsedSize);
        }
        return $this->calcShortRoi($lastPrice, $this->getUsedSize(), $this->maxUsedSize);
    }

    protected function assertCanRecalculateRoi(): void
    {
        if (!$this->isOpen())
        {
            throw new \LogicException('ROI for a closed position can not be recalculated.');
        }
    }

    protected function calcLongRoi(float $exitPrice, float $usedSize, float $maxUsedSize): float
    {
        if ($exitPrice == 0)
        {
            $pnl = $this->pnl - $usedSize;
        }
        else
        {
            $pnl = $this->amount * $exitPrice - $usedSize + $this->pnl;
        }

        return $pnl / $maxUsedSize * 100;
    }

    protected function calcShortRoi(float $exitPrice, float $usedSize, float $maxUsedSize): float
    {
        $size = $this->pnl + $usedSize;
        if ($exitPrice == 0)
        {
            $pnl = $size;
        }
        else
        {
            $pnl = $size - $this->amount * $exitPrice;
        }
        return $pnl / $maxUsedSize * 100;
    }

    public function relativeRoi(float $lastPrice): float
    {
        $this->assertCanRecalculateRoi();

        $usedSize = $this->getUsedSize();

        if ($this->isBuy())
        {
            return $this->calcLongRoi($lastPrice, $usedSize, self::MAX_SIZE);
        }

        return $this->calcShortRoi($lastPrice, $usedSize, self::MAX_SIZE);
    }

    public function stop(int $exitTime): void
    {
        if ($this->isStopped)
        {
            throw new \LogicException('Attempted to stop an already stopped position.');
        }

        $exitTime = Calc::asMs($exitTime);

        $this->lockIfUnlocked($this->stop);
        $this->exitPrice = $this->stop->get();

        $this->saveRoi($this->exitPrice);

        $this->isStopped = true;
        $this->exitTime = $exitTime;

        $this->insertStopTransaction($exitTime);

        $this->fireEvent('stop');
    }

    public function relativeExitRoi(): float
    {
        return $this->relativeRoi;
    }

    public function roiEntry(float $lastPrice): float
    {
        return Calc::roi($this->isBuy(), $this->entry->get(), $lastPrice);
    }

    public function exitRoi(): float
    {
        return $this->roi;
    }

    public function entryTime(): int
    {
        return $this->entryTime;
    }

    public function increaseSize(float $size, float $price, int $timestamp, string $reason): void
    {
        $this->newTransaction(true, $price, $size, $timestamp, $reason);
    }

    public function decreaseSize(float $size, float $price, int $timestamp, string $reason): void
    {
        $this->newTransaction(false, $price, $size, $timestamp, $reason);
    }

    public function price(string $type): ?Price
    {
        return $this->{$type};
    }

    public function transactionLog(): ChangeLog
    {
        return $this->transactionLog;
    }

    public function isBuy(): bool
    {
        return $this->side->isBuy();
    }

    public function getMaxUsedSize(): float|int
    {
        return $this->maxUsedSize;
    }

    protected function enter(): void
    {
        $this->newTransaction(true,
            $this->entry->get(),
            $this->size,
            $this->entryTime,
            'Position entry');
    }

    protected function assertNotGreaterThanUsedSize(float $size): void
    {
        if ($size > $this->getUsedSize())
        {
            throw new \LogicException('Reduce size can not be greater than used size.');
        }
    }

    protected function insertStopTransaction(int $exitTime): void
    {
        $this->newTransaction(false,
            $this->exitPrice,
            $this->getUsedSize(),
            $exitTime,
            'Position stop.');
    }

    /**
     * @param int $exitTime
     *
     * @return void
     */
    protected function insertExitTransaction(int $exitTime): void
    {
        $this->newTransaction(false,
            $this->exitPrice,
            $this->getUsedSize(),
            $exitTime,
            'Position exit.');
    }
}