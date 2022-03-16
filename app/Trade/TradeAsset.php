<?php

declare(strict_types=1);

namespace App\Trade;

use App\Trade\Evaluation\Position;
use App\Trade\Exchange\Account\Asset;
use App\Trade\Exchange\Account\Balance;

class TradeAsset
{
    public function __construct(public readonly Balance $balance,
                                public readonly Asset $asset,
                                public readonly float $allocation)

    {
        if ($this->asset->available() < $this->allocation)
        {
            throw new \LogicException('Allocated asset amount exceeds available amount.');
        }
    }

    public function update(): static
    {
        $this->balance->update();

        return $this;
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