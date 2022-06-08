<?php

declare(strict_types=1);

namespace App\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Enum\OrderType;

class Market extends Handler
{
    public function getOrderType(): OrderType
    {
        return OrderType::MARKET;
    }

    protected function handle(float $quantity, float $price, bool $reduceOnly): Order
    {
        return $this->manager->market($this->getSide($reduceOnly), $quantity, $reduceOnly);
    }
}
