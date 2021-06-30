<?php

namespace App\Trade\Exchange\Response;

use App\Models\Order;

/**
 * @property float price
 */
class NewOrderResponse extends AbstractExchangeResponse
{
    protected array $expectedKeys = ['price'];

    public function updateOrderState(Order $order): void
    {
        if (empty($order->response))
        {
            $order->response = $this->data;
        }

        $order->price = $this->price;
    }
}