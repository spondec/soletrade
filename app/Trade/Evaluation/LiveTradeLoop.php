<?php

namespace App\Trade\Evaluation;

use App\Models\Fill;
use App\Models\Order;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Enum\OrderType;
use App\Trade\Exchange\Account\TradeAsset;
use App\Trade\Exchange\OrderManager;
use App\Trade\Log;

class LiveTradeLoop extends TradeLoop
{
    public function __construct(TradeSetup                   $entry,
                                Symbol                       $evaluationSymbol,
                                array                        $config,
                                public readonly OrderManager $order)
    {
        parent::__construct($entry, $evaluationSymbol, $config);
    }

    public function __destruct()
    {
        if ($this->status->getPosition() && !$this->status->isExited())
        {
            throw new \LogicException('TradeLoop can not be destroyed before the trade is exited');
        }
    }

    protected function tryPositionEntry(\stdClass $candle, int $priceDate): void
    {
        if (!$this->order->entry)
        {
            $price = $this->status->getEntryPrice();
            $order = $this->sendEntryOrder($price);

            $price->lock();

            $order->onFill(function (Fill $fill) {
                if ($this->status->isEntered())
                {
                    /** @var LivePosition $position */
                    $position = $this->getPosition();
                    $position->processEntryOrderFill($fill);
                }
                else
                {
                    $this->status->enterPosition($fill->timestamp,
                        $this->asset()->proportional($fill->quantity * $fill->price),
                        $fill->price,
                        LivePosition::class,
                        $this->order,
                        $fill);
                }
            });
        }
        else if (!$this->order->entry->isAllFilled())
        {
            $this->order->sync($this->order->entry);
        }
    }

    protected function sendEntryOrder(Price $price): Order
    {
        if ($this->order->entry)
        {
            throw new \LogicException('Entry order already sent.');
        }

        $orderType = $this->entry->entry_order_type;
        $orderPrice = $price->get();

        return $this->order->entry = $this->order
            ->handler($orderType, $this->entry->side())
            ->order($orderType, $this->asset()->quantity($orderPrice, $this->entry->size), $orderPrice, false);
    }

    protected function asset(): TradeAsset
    {
        return $this->order->tradeAsset;
    }

    protected function tryPositionExit(Position $position, \stdClass $candle, int $priceDate): void
    {
        $isEntryFilled = $this->order->entry->isAllFilled();

        if (!$this->order->exit && $this->status->getTargetPrice())
        {
            if (!$isEntryFilled)
            {
                Log::info("Entry order not filled fully, cannot send exit order.");
                return;
            }

            Log::info('Sending target order...');
            $this->live()->sendExitOrder(OrderType::LIMIT);
        }

        if (!$this->order->stop && $this->status->getStopPrice())
        {
            if (!$isEntryFilled)
            {
                Log::info("Entry order not filled fully, cannot send stop order.");
                return;
            }

            Log::info('Sending stop order...');
            $this->live()->sendStopOrder(OrderType::STOP_LIMIT);
        }

        parent::tryPositionExit($position, $candle, $priceDate);

        $this->order->syncAll();
    }

    protected function live(): LivePosition
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::getPosition();
    }
}