<?php

namespace App\Trade\Collection;

use App\Models\TradeSetup;
use App\Trade\HasConfig;
use Illuminate\Support\Collection;

class TradeCollection extends Collection
{
    use HasConfig;

    protected array $config = [
        'oppositeOnly'  => false,
        'permanentOnly' => false,
    ];

    public function __construct($items = [], array $config = [])
    {
        parent::__construct($items);
        $this->mergeConfig($config);

        if ($this->config('permanentOnly'))
        {
            $this->items = array_filter($this->items, fn(TradeSetup $trade) => $trade->is_permanent);
        }
    }

    public function mergeTrades(TradeCollection $trades): static
    {
        foreach ($trades as $timestamp => $trade)
        {
            $this->items[$timestamp] = $trade;
        }

        return $this;
    }

    public function cleanUpBefore(TradeSetup $setup): void
    {
        foreach ($this->items as $k => $item)
        {
            if ($item->id == $setup->id)
            {
                return;
            }

            unset($this->items[$k]);
        }
    }

    public function getFirstTrade(): ?TradeSetup
    {
        return $this->first();
    }

    public function getNextTrade(TradeSetup $tradeSetup): ?TradeSetup
    {
        if ($this->config('oppositeOnly'))
        {
            return $this->findNextOppositeTrade($tradeSetup);
        }
        return $this->findNextTrade($tradeSetup);
    }

    protected function findNextOppositeTrade(TradeSetup $tradeSetup): ?TradeSetup
    {
        $isBuy = $tradeSetup->isBuy();

        while ($next = $this->findNextTrade($next ?? $tradeSetup))
        {
            if ($next->isBuy() !== $isBuy)
            {
                return $next;
            }
        }

        return null;
    }

    protected function findNextTrade(TradeSetup $trade): ?TradeSetup
    {
        $timestamp = $trade->timestamp;
        $iterator = $this->getIterator();

        while ($iterator->valid())
        {
            if ($iterator->current()->timestamp == $timestamp)
            {
                $iterator->next();
                return $iterator->current();
            }

            $iterator->next();
        }

        return null;
    }
}