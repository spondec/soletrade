<?php

namespace App\Trade;

use App\Models\Order;
use App\Models\Position;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Database\Eloquent\Collection;

class Manager
{
    protected static array $exchanges;

    /** @var Position[] */
    protected Collection $positions;

    /** @var Order[] */
    protected Collection $orders;

    protected static ?self $instance = null;

    private function __construct()
    {
        $this->positions = Position::query()
            ->where('open', true)
            ->get()
            ->groupBy('symbol');

        $this->orders = Order::query()
            ->where('open', true)
            ->get()
            ->groupBy('symbol');
    }

    public static function instance()
    {
        if (static::$instance)
        {
            return static::$instance;
        }

        return static::$instance = new static();
    }

    /**
     *
     * Open a position with TradeSetup.
     *
     * @param TradeSetup $setup
     *
     * @return Position|null
     */
    public function open(TradeSetup $setup, AbstractExchange $exchange, float $size): ?bool
    {
        $order = $exchange->limit($setup->side,
            $setup->symbol,
            $setup->price,
            $size);

        if (!$order)
        {
            return null;
        }

        $this->orders['entry'][$order->id] = $order;
        return true;
    }

    public function getTotalOpenPositionSize(): array
    {
        $size = [];

        foreach ($this->positions as $position)
        {
            $type = $position->quantity_type;

            if (!isset($size[$type]))
            {
                $size[$type] = 0;
            }

            $size[$type] += $position->quantity;
        }

        return $size;
    }

    /**
     * Close the position.
     *
     * @param Position $position
     * @param float    $price
     *
     * @return bool
     */
    public function close(Position $position, float $price = null): bool
    {
        $closePrice = $price ?? $position->exchange()->price($symbol = $position->symbol);

        if (!$closePrice)
        {
            throw new \UnexpectedValueException("Price for $symbol couldn't be fetched from the exchange.");
        }

        $order = $position->exchange()->limit($position->getCloseAction(),
            $symbol,
            $closePrice,
            $position->quantity);

        if (!$order) return false;

        $order->trade_setup_id = $position->trade_setup_id;

        $this->orders['close'][$order->id] = $order;
        return true;
    }

    /**
     * Place a take profit order.
     *
     * @param Position $position
     * @param float    $price
     * @param float    $amount
     *
     * @return bool
     */
    public function takeProfit(Position $position, float $price, float $amount): bool
    {

    }

    /**
     * Place a stop loss order.
     *
     * @param Position $position
     * @param float    $price
     */
    public function stopLoss(Position $position, float $price): bool
    {

    }
}