<?php

namespace App\Trade\Collection;

use App\Models\TradeSetup;
use App\Trade\HasConfig;
use Illuminate\Support\Collection;

/**
 * @property TradeSetup[] items
 */
class TradeCollection extends Collection
{
    use HasConfig;

    protected array $config = [
        'oppositeOnly'  => false,
        'permanentOnly' => false,
    ];

    /**
     * @param TradeSetup[] $items
     * @param array        $config
     *
     * @throws \Exception
     */
    public function __construct($items = [], array $config = [])
    {
        $this->mergeConfig($config);

        $items = $this->filterByConfig($items);
        $items = $this->keyByTimestamp($items);
        ksort($items);

        parent::__construct($items);
    }

    /**
     * @param TradeCollection<TradeSetup> $trades
     *
     * @return $this
     */
    public function mergeTrades(TradeCollection $trades): static
    {
        foreach ($trades as $timestamp => $trade)
        {
            $this->items[$timestamp] = $trade;
        }

        ksort($this->items);

        return $this;
    }

    public function cleanUpBefore(TradeSetup $trade): void
    {
        foreach ($this->items as $t => $_trade)
        {
            if ($_trade->id == $trade->id)
            {
                return;
            }

            unset($this->items[$t]);
        }
    }

    public function getFirstTrade(): ?TradeSetup
    {
        return $this->first();
    }

    public function getNextTrade(TradeSetup $trade): ?TradeSetup
    {
        if ($this->config('oppositeOnly'))
        {
            return $this->findNextOppositeTrade($trade);
        }
        return $this->findNextTrade($trade);
    }

    protected function findNextOppositeTrade(TradeSetup $trade): ?TradeSetup
    {
        $isBuy = $trade->isBuy();

        while ($next = $this->findNextTrade($next ?? $trade))
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
            if ($iterator->key() == $timestamp)
            {
                $iterator->next();
                return $iterator->current();
            }

            $iterator->next();
        }

        throw new \InvalidArgumentException('Argument $trade is not in collection.');
    }

    /**
     * @param TradeSetup[] $items
     *
     * @return array
     * @throws \Exception
     */
    protected function keyByTimestamp(array $items): array
    {
        $keyed = [];
        foreach ($items as $trade)
        {
            $t = $trade->timestamp;
            if (isset($keyed[$t]))
            {
                throw new \Exception('Duplicate trade timestamp.');
            }

            $keyed[$t] = $trade;
        }
        return $keyed;
    }

    /**
     * @param array $items
     *
     * @return TradeSetup[]|array
     */
    protected function filterByConfig(array $items): array
    {
        if ($this->config('permanentOnly'))
        {
            $items = array_filter($items, fn(TradeSetup $trade) => $trade->is_permanent);
        }
        return $items;
    }
}