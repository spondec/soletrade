<?php

namespace App\Trade;

use App\Models\Order;
use App\Models\Position;
use App\Models\Signal;
use App\Trade\Exchange\Futures\AbstractFuturesExchange;
use App\Trade\Exchange\Spot\AbstractSpotExchange;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Database\Eloquent\Collection;

class Trader
{
    /** @var Position[] */
    protected Collection $positions;
    /** @var Order[] */
    protected Collection $orders;

    public function __construct(
        protected AbstractStrategy        $strategy,
        protected AbstractSpotExchange    $spot,
        protected AbstractFuturesExchange $futures)
    {
        $this->positions = Position::query()->where('open', true)->get();
        $this->orders = Order::query()->where('open', true)->get();
    }

    public function openPosition(Signal $setup): ?Position
    {

    }

    public function closePosition(Position $position, float $price): bool
    {

    }

    public function takeProfit(Position $position, float $price, float $amount): bool
    {

    }

    public function stopLoss(Position $position, float $price)
    {

    }
}