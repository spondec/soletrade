<?php

namespace App\Trade\Evaluation;

use App\Models\Fill;
use App\Models\Order;
use App\Models\OrderType;
use App\Trade\Calc;
use App\Trade\OrderManager;
use App\Trade\Side;
use App\Trade\TradeAsset;

class LivePosition extends Position
{
    public OrderType $exitOrderType = OrderType::LIMIT;
    public OrderType $stopOrderType = OrderType::STOP_LIMIT;

    public OrderType $increaseOrderType = OrderType::LIMIT;
    public OrderType $decreaseOrderType = OrderType::LIMIT;

    protected float $ownedQuantity = 0;

    public function __construct(Side                   $side,
                                float                  $size,
                                int                    $entryTime,
                                Price                  $entry,
                                ?Price                 $exit,
                                ?Price                 $stop,
                                protected OrderManager $manager,
                                Fill                   $fill)
    {

        if ($entry->get() != $fill->price)
        {
            throw new \LogicException('Fill price does not match entry price.');
        }

        if ($size != $this->asset()->proportional($fill->quantity * $fill->price))
        {
            throw new \LogicException('Fill quantity does not match size.');
        }

        $this->ownedQuantity += $fill->quantity;

        parent::__construct($side,
            $size,
            $entryTime,
            $entry,
            $exit,
            $stop);
        $this->registerPriceChangeListeners();
    }

    protected function registerPriceChangeListeners(): void
    {
        $this->registerExitPriceListeners();

        $this->registerStopPriceListeners();
    }

    protected function registerExitPriceListeners(): void
    {
        $this->exit?->listen('changed', function (Price $price) {
            if ($this->manager->exit)
            {
                $this->resendExitOrder();
            }
        });
    }

    public function resendExitOrder(?OrderType $orderType = null): Order
    {
        $this->manager->cancel($this->manager->exit);

        return $this->sendExitOrder($orderType);
    }

    public function sendExitOrder(?OrderType $orderType = null): Order
    {
        $this->assertExitOrderNotSent();

        if (!$this->exit)
        {
            throw new \LogicException('Exit price was not set.');
        }

        $price = $this->exit->get();
        $this->exit->lock();

        $order = $this->sendDecreaseOrder($orderType ?? $this->exitOrderType, $this->getUsedSize(), $price, 'Exit order fill.');

        $order->onFill(function (Fill $fill) use ($order) {
            if ($order->isAllFilled())
            {
                /** @var Price $exitPrice */
                $exitPrice = $this->price('exit');
                $exitPrice->bypassEventOnce('changed');
                $exitPrice->set($order->avgFillPrice(), Calc::asMs(time()), 'Exit order price.', true);
                parent::close($fill->timestamp);
            }
        });

        return $order;
    }

    protected function assertExitOrderNotSent(): void
    {
        if ($this->manager->exit)
        {
            throw new \LogicException('Exit order already sent.');
        }
    }

    protected function sendDecreaseOrder(OrderType $orderType, float $proportionalSize, float $price, string $reason): Order
    {
        $this->assertNotGreaterThanUsedSize($proportionalSize);

        $quantity = $this->ownedQuantity / $this->getUsedSize() * $proportionalSize;

        $order = $this->order($orderType, $quantity, $price, true);

        $order->onFill(function (Fill $fill) use ($reason) {
            parent::decreaseSize($this->proportional($fill->quantity * $this->getBreakEvenPrice()),
                $fill->price,
                $fill->timestamp,
                $reason);

            $this->ownedQuantity -= $fill->quantity;
        });

        return $order;
    }

    public function getOwnedQuantity(): float
    {
        return $this->ownedQuantity;
    }

    protected function order(OrderType $orderType,
                             float     $quantity,
                             float     $price,
                             bool      $reduceOnly): Order
    {
        return $this->manager
            ->handler($orderType, $this->side)
            ->order($orderType, $quantity, $price, $reduceOnly);
    }

    public function proportional(float $realSize): float
    {
        return $this->asset()->proportional($realSize);
    }

    public function asset(): TradeAsset
    {
        return $this->manager->tradeAsset;
    }

    protected function registerStopPriceListeners(): void
    {
        $this->stop?->listen('changed', function (Price $price) {
            if ($this->manager->stop)
            {
                $this->resendStopOrder();
            }
        });
    }

    public function resendStopOrder(?OrderType $orderType = null): Order
    {
        $this->manager->cancel($this->manager->stop);

        return $this->sendStopOrder($orderType);
    }

    public function sendStopOrder(?OrderType $orderType = null): Order
    {
        $this->assertStopOrderNotSent();

        if (!$this->stop)
        {
            throw new \LogicException('Stop price was not set.');
        }

        $price = $this->stop->get();
        $this->stop->lock();

        $order = $this->sendDecreaseOrder($orderType ?? $this->stopOrderType,
            $this->getUsedSize(),
            $price,
            'Stop order fill.');

        $order->onFill(function (Fill $fill) use ($order) {
            if ($order->isAllFilled())
            {
                /** @var Price $stopPrice */
                $stopPrice = $this->price('stop');
                $stopPrice->bypassEventOnce('changed');
                $stopPrice->set($order->avgFillPrice(), Calc::asMs(time()), 'Stop order price.', true);
                parent::stop($fill->timestamp);
            }
        });

        return $order;
    }

    protected function assertStopOrderNotSent(): void
    {
        if ($this->manager->stop)
        {
            throw new \LogicException('Stop order already sent.');
        }
    }

    public function processEntryOrderFill(Fill $fill): void
    {
        parent::increaseSize($this->proportional($fill->quantity * $this->getBreakEvenPrice()),
            $fill->price,
            $fill->timestamp,
            'Entry order fill.');

        $this->ownedQuantity += $fill->quantity;
    }

    public function stop(int $exitTime): void
    {
        if ($this->stop)
        {
            $this->resendStopOrder(OrderType::MARKET);
        }
        else
        {
            $this->sendStopOrder(OrderType::MARKET);
        }
    }

    public function close(int $exitTime): void
    {
        if ($this->exit)
        {
            $this->resendExitOrder(OrderType::MARKET);
        }
        else
        {
            $this->sendExitOrder(OrderType::MARKET);
        }
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
            parent::increaseSize($this->proportional($fill->quantity * $this->getBreakEvenPrice()),
                $fill->price,
                $fill->timestamp,
                $reason);

            $this->ownedQuantity += $fill->quantity;
        });

        return $order;
    }

    public function decreaseSize(float $size, float $price, int $timestamp, string $reason): void
    {
        $this->sendDecreaseOrder($this->decreaseOrderType, $size, $price, $reason);
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
        try
        {
            parent::newTransaction($increase, $price, $size, $timestamp, $reason);
        } catch (\LogicException $e)
        {
            if (str_contains($e->getMessage(), 'Position is open but no asset left'))
            {
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