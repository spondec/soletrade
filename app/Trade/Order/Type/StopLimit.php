<?php

declare(strict_types=1);

namespace App\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;

class StopLimit extends Handler
{
    public const DEFAULT_TRIGGER_PRICE_RATIO = 0.001;

    protected function getDefaultConfig(): array
    {
        return [
            'trigger_price_ratio' => static::DEFAULT_TRIGGER_PRICE_RATIO,
        ];
    }

    public function getOrderType(): OrderType
    {
        return OrderType::STOP_LIMIT;
    }

    protected function handle(float $quantity, float $price, bool $reduceOnly): Order
    {
        $side = $this->getSide($reduceOnly);

        return $this->manager->stopLimit($side,
            $this->getStopPrice($side, $price, $this->config('trigger_price_ratio', true)),
            $price,
            $quantity,
            $reduceOnly);
    }

    protected function getStopPrice(Side $side, float $price, float $stopPriceRatio): float
    {
        $spread = $price * $stopPriceRatio;

        return $side->isBuy() ? $price - $spread : $price + $spread;
    }
}
