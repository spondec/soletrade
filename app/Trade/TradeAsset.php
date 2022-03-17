<?php

declare(strict_types=1);

namespace App\Trade;

use App\Trade\Evaluation\Position;
use App\Trade\Exchange\Account\Asset;
use App\Trade\Exchange\Account\Balance;

class TradeAsset
{
    protected float $available;

    public function __construct(public readonly Balance $balance,
                                public readonly Asset $asset,
                                public readonly float $allocation)

    {
        if ($this->asset->available() < $this->allocation)
        {
            throw new \LogicException('Allocated asset amount exceeds available amount.');
        }
        $this->setAvailable($this->allocation);
    }

    public function update(): static
    {
        $this->balance->update();

        return $this;
    }

    public function cutAvailable(float $realSize)
    {
        $this->setAvailable($this->available - $realSize);
    }

    public function addAvailable(float $realSize)
    {
        $this->setAvailable($this->available + $realSize);
    }

    /**
     * Sets available real size.
     *
     * @param float $available
     *
     * @return void
     */
    private function setAvailable(float $available): void
    {
        if ($available > $this->allocation)
        {
            throw new \LogicException('Available amount exceeds allocated amount.');
        }

        if ($available < 0)
        {
            throw new \LogicException('Available amount cannot be negative.');
        }

        $this->available = $available;
    }

    /**
     * Gets available real size.
     *
     * @return float
     */
    public function available(): float
    {
        return $this->available;
    }

    public function getRealSize(float $proportionalSize): float
    {
        $this->assertGreaterThanZero($proportionalSize);
        $this->assertLessThanMaxPositionSize($proportionalSize);

        return $this->allocation * $proportionalSize / Position::MAX_SIZE;
    }

    private function assertGreaterThanZero(float $value): void
    {
        if ($value <= 0)
        {
            throw new \LogicException('Argument $value must be greater than zero.');
        }
    }

    private function assertLessThanMaxPositionSize(float $proportionalSize): void
    {
        if ($proportionalSize > Position::MAX_SIZE)
        {
            throw new \LogicException('Argument $proportionalSize exceeds the maximum position size.');
        }
    }

    public function getProportionalSize(float $size): float
    {
        $this->assertGreaterThanZero($size);
        $this->assertLessThanAllocation($size);

        return $size / $this->allocation * Position::MAX_SIZE;
    }

    private function assertLessThanAllocation(float $size): void
    {
        if ($size > $this->allocation)
        {
            throw new \LogicException('Argument $size exceeds the allocated asset amount.');
        }
    }
}