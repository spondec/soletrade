<?php

namespace App\Trade\Contracts\Exchange;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Side;

interface Orderer
{
    /**
     * @param Order $order
     *
     * @return Fill[]
     */
    public function sync(Order $order): array;

    public function cancel(Order $order): Order;

    public function market(Side   $side,
                           string $symbol,
                           float  $quantity,
                           bool   $reduceOnly): Order;

    public function stopMarket(Side   $side,
                               string $symbol,
                               float  $quantity,
                               float  $stopPrice,
                               bool   $reduceOnly): Order;

    public function limit(Side   $side,
                          string $symbol,
                          float  $price,
                          float  $quantity,
                          bool   $reduceOnly): Order;

    public function stopLimit(Side   $side,
                              string $symbol,
                              float  $stopPrice,
                              float  $price,
                              float  $quantity,
                              bool   $reduceOnly): Order;
}