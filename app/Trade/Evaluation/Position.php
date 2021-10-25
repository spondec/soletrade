<?php

namespace App\Trade\Evaluation;

use App\Trade\Calc;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;

class Position
{
    public const MAX_SIZE = 100;

    protected bool $isStopped = false;
    protected bool $isClosed = false;

    protected float $remainingSize;

    protected ?int $exitTime = null;

    protected Collection $trades;

    protected float $multiplier = 1;
    protected float $amount = 0;

    protected float $pnl = 0;
    protected float $roi;
    protected float $relativeRoi;

    protected float $maxUsedSize = 0;

    public function __construct(protected bool  $isBuy,
                                protected float $size,
                                protected int   $entryTime,
                                protected Price $entry,
                                protected Price $exit,
                                protected Price $stop)
    {
        $this->remainingSize = static::MAX_SIZE;
        $this->trades = new Collection();
        $this->assertSize($this->size);
        $this->newTrade(true, $entry->get(), $size);
        $this->entry->lock($this);
    }

    protected function assertSize(float $size): void
    {
        if ($size > static::MAX_SIZE)
        {
            throw new \InvalidArgumentException('Maximum position size is limited to ' . static::MAX_SIZE);
        }
    }

    protected function newTrade(bool $increase, float $price, float $size): void
    {
        if ($increase)
        {
            $this->assertRemainingSize($size);
            $this->maxUsedSize += $size;
            $this->remainingSize -= $size;
            $this->amount += $size / $price;
        }
        else
        {
            if ($size > $this->getUsedSize())
            {
                throw new \InvalidArgumentException('Reduce size can not be greater than used size.');
            }

            $reduce = $this->amount / $this->getUsedSize() * $size;
            $pnl = $reduce * $price - $reduce * $this->getBreakEvenPrice();
            if (!$this->isBuy)
            {
                $pnl *= -1;
            }
            $this->pnl += $pnl;
            $this->amount -= $reduce;
            $this->remainingSize += $size;
        }

        $this->trades[] = [
            'increase' => $increase,
            'price'    => $price,
            'size'     => $size,
        ];

        if ($this->isOpen() && !$this->getAssetAmount())
        {
            throw new \LogicException('Position is open but no asset left. Exit trades must be performed with stop() or close().');
        }
    }

    protected function assertRemainingSize(float $size): void
    {
        if ($size > $this->remainingSize)
        {
            throw new \InvalidArgumentException('The requested size is bigger than the remaining size.');
        }
    }

    public function getUsedSize(): float
    {
        return static::MAX_SIZE - $this->remainingSize;
    }

    #[Pure] public function getBreakEvenPrice(): float
    {
        $usedSize = $this->getUsedSize();
        $totalSize = $usedSize + $this->pnl;

        $breakEvenRoi = ($usedSize - $totalSize) * 100 / $usedSize;
        $breakEvenPrice = $usedSize / $this->amount;

        $differ = ($breakEvenPrice * $breakEvenRoi / 100);

        if ($this->isBuy)
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

    public function setMultiplier(float $multiplier): void
    {
        $this->multiplier = $multiplier;
    }

    public function exitTime(): int
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

    public function close(int $exitTime): void
    {
        if ($this->isClosed)
        {
            throw new \LogicException('Attempted to close a position that is already closed.');
        }
        $this->exit->lock($this);
        $exitPrice = $this->exit->get();
        $this->saveRoi($exitPrice);
        $this->isClosed = true;
        $this->exitTime = $exitTime;
        $this->newTrade(false, $exitPrice, $this->getUsedSize());
    }

    protected function saveRoi(float $price): void
    {
        $this->roi = $this->roi($price);
        $this->relativeRoi = $this->relativeRoi($price);
    }

    public function roi(float $lastPrice): float
    {
        $this->assertCanRecalculateRoi();

        if ($this->isBuy)
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

        if ($this->isBuy)
        {
            return $this->calcLongRoi($lastPrice, $usedSize, self::MAX_SIZE);
        }

        return $this->calcShortRoi($lastPrice, $usedSize, self::MAX_SIZE);
    }

    public function exitRelativeRoi(): float
    {
        return $this->relativeRoi;
    }

    #[Pure] public function roiEntry(float $lastPrice): float
    {
        return Calc::roi($this->isBuy, $this->entry->get(), $lastPrice);
    }

    public function exitRoi(): float
    {
        return $this->roi;
    }

    public function stop(int $exitTime): void
    {
        if ($this->isStopped)
        {
            throw new \LogicException('Attempted to stop a position that is already stopped.');
        }
        $this->stop->lock($this);
        $stopPrice = $this->stop->get();
        $this->saveRoi($stopPrice);
        $this->isStopped = true;
        $this->exitTime = $exitTime;
        $this->newTrade(false, $stopPrice, $this->getUsedSize());
    }

    public function entryTime(): int
    {
        return $this->entryTime;
    }

    public function increaseSize(float $size, float $price): void
    {
        $this->newTrade(true, $price, $size);
    }

    public function decreaseSize(float $size, float $price): void
    {
        $this->newTrade(false, $price, $size);
    }

    public function price(string $type): Price
    {
        return $this->{$type};
    }
}