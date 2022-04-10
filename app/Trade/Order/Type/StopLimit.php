<?php

declare(strict_types=1);

namespace App\Trade\Order\Type;

use App\Models\Order;
use App\Models\OrderType;
use App\Trade\Side;

class StopLimit extends Handler
{
    public float $ratio = 0.001;

    public function getOrderType(): OrderType
    {
        return OrderType::STOP_LIMIT;
    }

    protected function handle(float $quantity, float $price, bool $reduceOnly): Order
    {
        $side = $this->getSide($reduceOnly);

        return $this->manager->stopLimit($side,
            $this->getStopPrice($side, $price, $this->ratio),
            $price,
            $quantity,
            $reduceOnly);
    }

    protected function getStopPrice(Side $side, float $price, float $spreadRatio): float
    {
        $spread = $price * $spreadRatio;
        return $side->isBuy() ? $price - $spread : $price + $spread;
    }
}