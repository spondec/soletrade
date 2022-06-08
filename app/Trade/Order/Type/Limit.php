<?php

declare(strict_types=1);

namespace App\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Enum\OrderType;

class Limit extends Handler
{
    public function getOrderType(): OrderType
    {
        return OrderType::LIMIT;
    }

    protected function handle(float $quantity, float $price, bool $reduceOnly): Order
    {
        return $this->manager->limit($this->getSide($reduceOnly), $price, $quantity, $reduceOnly);
    }
}
