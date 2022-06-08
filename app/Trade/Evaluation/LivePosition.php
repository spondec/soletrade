<?php

namespace App\Trade\Evaluation;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Enum\OrderStatus;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Exchange\Account\TradeAsset;
use App\Trade\Exchange\OrderManager;
use App\Trade\Log;
use Illuminate\Support\Collection;

class LivePosition extends Position
{
    public OrderType $exitOrderType = OrderType::LIMIT;
    public OrderType $stopOrderType = OrderType::STOP_LIMIT;

    public OrderType $increaseOrderType = OrderType::LIMIT;
    public OrderType $decreaseOrderType = OrderType::LIMIT;

    protected float $ownedQuantity = 0;
    protected float $entryPrice;

    public function __construct(
        Side $side,
        float $size,
        int $entryTime,
        Price $entry,
        ?Price $exit,
        ?Price $stop,
        protected OrderManager $manager,
        Fill $fill
    )
    {
        $this->entryPrice = $entry->get();

        if ($this->entryPrice != $fill->price) {
            throw new \LogicException('Fill price does not match entry price.');
        }

        if ($size != $this->asset()->proportional($fill->quantity * $fill->price)) {
            throw new \LogicException('Fill quantity does not match size.');
        }

        $this->ownedQuantity += $fill->quantity;

        parent::__construct(
            $side,
            $size,
            $entryTime,
            $entry,
            $exit,
            $stop
        );
        $this->registerPriceChangeListeners();
    }

    public function proportional(float $realSize): float
    {
        return $this->asset()->proportional($realSize);
    }

    public function asset(): TradeAsset
    {
        return $this->manager->tradeAsset;
    }

    protected function registerPriceChangeListeners(): void
    {
        $this->registerExitPriceListeners();

        $this->registerStopPriceListeners();
    }

    protected function registerExitPriceListeners(): void
    {
        $this->exit?->listen('changed', function (Price $price) {
            if ($this->manager->exit) {
                $this->resendExitOrder();
            }
        });
    }

    public function resendExitOrder(?OrderType $orderType = null): Order
    {
        if (!$order = $this->manager->exit) {
            throw new \LogicException('Cannot resend exit order without an present exit order.');
        }

        if (!$this->cancelOrder($order, $error)) {
            return $order;
        }

        return $this->sendExitOrder($orderType);
    }

    protected function cancelOrder(Order $order, ?\Throwable &$error = null): bool
    {
        if (!$order->isOpen()) {
            throw new \LogicException('Attempted to cancel a closed order.');
        }

        try {
            $this->manager->cancel($order);
        } catch (\App\Trade\Exception\OrderFilledInCancelRequest $error) {
            //Order should be filled fully, fill listeners will handle the rest.
            //Do not resend the order.
            $this->assertOrderFilledFully($order);

            return false;
        } finally {
            if ($error) {
                Log::error($error);
            }
        }

        return $order->status === OrderStatus::CANCELED;
    }

    protected function assertOrderFilledFully(Order $order): void
    {
        if (!$order->isAllFilled()) {
            throw new \LogicException('Order supposed to be filled but was not. Order ID: '.$order->id);
        }
    }

    public function sendExitOrder(?OrderType $orderType = null): Order
    {
        $orderType = $orderType ?? $this->exitOrderType;
        $this->assertExitOrderNotSent();
        $this->assertExitPriceSet($orderType);

        $price = $this->exit?->get() ?? throw new \LogicException('Exit price not set.');

        $order = $this->sendDecreaseOrder($orderType, $this->getUsedSize(), $price, 'Exit order fill.');

        $order->onFill(function (Fill $fill) use ($order) {
            if ($order->isAllFilled()) {
                /** @var Price $exitPrice */
                $exitPrice = $this->price('exit');
                $exitPrice->bypassEventOnce('changed');
                $exitPrice->set($order->avgFillPrice(), as_ms(\time()), 'Exit order price.', true);
                parent::close($fill->timestamp);
            }
        });

        return $this->manager->exit = $order;
    }

    protected function assertExitOrderNotSent(): void
    {
        if ($this->manager->exit) {
            throw new \LogicException('Exit order already sent.');
        }
    }

    /**
     * @return void
     */
    protected function assertExitPriceSet(OrderType $orderType): void
    {
        if (!$this->exit && $orderType !== OrderType::MARKET) {
            throw new \LogicException('Exit price was not set.');
        }
    }

    protected function sendDecreaseOrder(OrderType $orderType, float $proportionalSize, float $price, string $reason): Order
    {
        $this->assertNotGreaterThanUsedSize($proportionalSize);

        $quantity = $this->ownedQuantity / $this->getUsedSize() * $proportionalSize;

        $order = $this->order($orderType, $quantity, $price, true);

        $order->onFill(function (Fill $fill) use ($reason) {
            parent::decreaseSize(
                $this->proportional($fill->quantity * $this->getBreakEvenPrice()),
                $fill->price,
                $fill->timestamp,
                $reason
            );

            $this->ownedQuantity -= $fill->quantity;
        });

        return $order;
    }

    protected function order(
        OrderType $orderType,
        float $quantity,
        float $price,
        bool $reduceOnly
    ): Order
    {
        return $this->manager
            ->handler($orderType, $this->side)
            ->order($orderType, $quantity, $price, $reduceOnly);
    }

    public function decreaseSize(float $size, float $price, int $timestamp, string $reason): void
    {
        $this->sendDecreaseOrder($this->decreaseOrderType, $size, $price, $reason);
    }

    public function close(int $exitTime): void
    {
        if ($this->exit && $this->manager->exit) {
            $this->resendExitOrder(OrderType::MARKET);
        } else {
            $this->sendExitOrder(OrderType::MARKET);
        }
    }

    protected function registerStopPriceListeners(): void
    {
        $this->stop?->listen('changed', function (Price $price) {
            if ($this->manager->stop) {
                $this->resendStopOrder();
            }
        });
    }

    public function resendStopOrder(?OrderType $orderType = null): Order
    {
        if (!$order = $this->manager->stop) {
            throw new \LogicException('Can not resend stop order without a stop order.');
        }

        Log::info('Resending stop order.');

        if (!$this->cancelOrder($order, $error)) {
            return $order;
        }

        return $this->sendStopOrder($orderType);
    }

    public function sendStopOrder(?OrderType $orderType = null): Order
    {
        $orderType = $orderType ?? $this->stopOrderType;
        $this->assertStopOrderNotSent();
        $this->assertStopPriceSet($orderType);

        $price = $this->stop?->get() ?? throw new \LogicException('Stop price not set.');

        $order = $this->sendDecreaseOrder(
            $orderType,
            $this->getUsedSize(),
            $price,
            'Stop order fill.'
        );

        $order->onFill(function (Fill $fill) use ($order) {
            if ($order->isAllFilled()) {
                /** @var Price $stopPrice */
                $stopPrice = $this->price('stop');
                $stopPrice->bypassEventOnce('changed');
                $stopPrice->set($order->avgFillPrice(), as_ms(\time()), 'Stop order price.', true);
                parent::stop($fill->timestamp);
            }
        });

        return $this->manager->stop = $order;
    }

    protected function assertStopOrderNotSent(): void
    {
        if ($this->manager->stop) {
            throw new \LogicException('Stop order already sent.');
        }
    }

    protected function assertStopPriceSet(OrderType $orderType): void
    {
        if (!$this->stop && $orderType !== OrderType::MARKET) {
            throw new \LogicException('Stop price was not set.');
        }
    }

    public function stop(int $exitTime): void
    {
        if ($this->stop && $this->manager->stop) {
            $this->resendStopOrder(OrderType::MARKET);
        } else {
            $this->sendStopOrder(OrderType::MARKET);
        }
    }

    /**
     * @return Collection<Order>
     */
    public function getOrders(): Collection
    {
        return $this->manager->orders();
    }

    public function getOwnedQuantity(): float
    {
        return $this->ownedQuantity;
    }

    public function getEntryPrice(): float
    {
        return $this->entryPrice;
    }

    public function processEntryOrderFill(Fill $fill): void
    {
        parent::increaseSize(
            $this->proportional($fill->quantity * ($this->entryPrice = $this->getBreakEvenPrice())),
            $fill->price,
            $fill->timestamp,
            'Entry order fill.'
        );

        $this->ownedQuantity += $fill->quantity;
    }

    public function increaseSize(float $size, float $price, int $timestamp, string $reason): void
    {
        $this->sendIncreaseOrder($this->increaseOrderType, $size, $price, $reason);
    }

    protected function sendIncreaseOrder(OrderType $orderType, float $proportionalSize, float $price, string $reason): Order
    {
        $this->assertNotGreaterThanRemaining($proportionalSize);

        $quantity = $this->asset()->quantity($price, $proportionalSize);

        $order = $this->order($orderType, $quantity, $price, false);

        $order->onFill(function (Fill $fill) use ($reason) {
            parent::increaseSize(
                $this->proportional($fill->quantity * $this->getBreakEvenPrice()),
                $fill->price,
                $fill->timestamp,
                $reason
            );

            $this->ownedQuantity += $fill->quantity;
        });

        return $order;
    }

    public function addStopPrice(Price $price): void
    {
        parent::addStopPrice($price);
        $this->registerStopPriceListeners();
    }

    public function addExitPrice(Price $price): void
    {
        parent::addExitPrice($price);
        $this->registerExitPriceListeners();
    }

    protected function newTransaction(bool $increase, float $price, float $size, int $timestamp, string $reason): void
    {
        try {
            parent::newTransaction($increase, $price, $size, $timestamp, $reason);
        } catch (\LogicException $e) {
            if (\str_contains($e->getMessage(), 'Position is open but no asset left')) {
                return;
            }

            throw $e;
        }
    }

    protected function insertExitTransaction(int $exitTime): void
    {
        //all transactions depend on order fills, emit any other transaction
    }

    protected function insertStopTransaction(int $exitTime): void
    {
        //all transactions depend on order fills, emit any other transaction
    }
}
