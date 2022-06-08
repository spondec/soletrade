<?php

namespace App\Trade\Contract\Exchange;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Enum\Side;
use App\Trade\Exception\OrderFailedException;

interface Orderer
{
    /**
     * @param Order $order
     *
     * @return Fill[]
     */
    public function sync(Order $order): array;

    /**
     * @param Order $order
     *
     * @return Order
     * @throws \App\Trade\Exception\OrderNotCanceledException
     * @throws \App\Trade\Exception\OrderFilledInCancelRequest
     */
    public function cancel(Order $order): Order;

    /**
     * @param Side   $side
     * @param string $symbol
     * @param float  $quantity
     * @param bool   $reduceOnly
     *
     * @return Order
     * @throws OrderFailedException
     */
    public function market(
        Side   $side,
        string $symbol,
        float  $quantity,
        bool   $reduceOnly
    ): Order;

    /**
     * @param Side   $side
     * @param string $symbol
     * @param float  $quantity
     * @param float  $stopPrice
     * @param bool   $reduceOnly
     *
     * @return Order
     * @throws OrderFailedException
     */
    public function stopMarket(
        Side   $side,
        string $symbol,
        float  $quantity,
        float  $stopPrice,
        bool   $reduceOnly
    ): Order;

    /**
     * @param Side   $side
     * @param string $symbol
     * @param float  $price
     * @param float  $quantity
     * @param bool   $reduceOnly
     *
     * @return Order
     * @throws OrderFailedException
     */
    public function limit(
        Side   $side,
        string $symbol,
        float  $price,
        float  $quantity,
        bool   $reduceOnly
    ): Order;

    /**
     * @param Side   $side
     * @param string $symbol
     * @param float  $stopPrice
     * @param float  $price
     * @param float  $quantity
     * @param bool   $reduceOnly
     *
     * @return Order
     * @throws OrderFailedException
     */
    public function stopLimit(
        Side   $side,
        string $symbol,
        float  $stopPrice,
        float  $price,
        float  $quantity,
        bool   $reduceOnly
    ): Order;
}
