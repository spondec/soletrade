<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\OrderType;
use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Candles;
use App\Trade\Config\TradeConfig;
use Illuminate\Support\Collection;

class TradeCreator
{
    public readonly array $signalIndicatorAliases;
    public readonly array $signalOrderMap;
    public readonly array $signalOrder;
    public readonly string $firstSignalClass;
    public readonly int $requiredSignalCount;

    protected ?Collection $actions = null;
    protected ?TradeSetup $trade = null;
    protected ?string $nextRequiredSignalAlias = null;
    protected Collection $signals;

    public function __construct(public TradeConfig $config)
    {
        $this->signals = new Collection();
        $this->signalIndicatorAliases = $config->getSignalIndicatorAliases();
        if ($this->requiredSignalCount = \count($this->signalIndicatorAliases))
        {
            $this->signalOrderMap = $this->getSignalOrderMap();
            $this->signalOrder = $this->getSignalOrder($this->signalOrderMap);
            $this->firstSignalClass = $this->nextRequiredSignalAlias = \array_key_first($this->signalOrderMap);
        }
    }

    protected function getSignalOrderMap(): array
    {
        $signalMap = [];
        $iterator = new \ArrayIterator($this->signalIndicatorAliases);

        while ($iterator->valid())
        {
            $current = $iterator->current();
            $iterator->next();
            $next = $iterator->current();
            $signalMap[$current] = $next;
        }

        return $signalMap;
    }

    public function setActions(Collection $actions): void
    {
        $this->actions = $actions;
    }

    public function setSymbol(Symbol $symbol)
    {
        $this->trade->symbol()->associate($symbol);
    }

    public function save(): TradeSetup
    {
        if (!$this->trade)
        {
            throw new \LogicException('Trade has not been set.');
        }

        /** @var TradeSetup $tradeSetup */
        $tradeSetup = $this->trade->updateUniqueOrCreate();
        $tradeSetup->actions()->delete();

        if ($this->actions)
        {
            foreach ($this->actions as $class => $config)
            {
                $tradeSetup->actions()->create(['class'  => $class,
                                                'config' => $config]);
            }
        }

        $tradeSetup->signals()
            ->sync($this->signals
                ->map(static fn(Signal $signal): int => $signal->id));
        $this->finalize();

        return $tradeSetup;
    }

    protected function finalize(): void
    {
        if ($this->requiredSignalCount)
        {
            $this->signals = new Collection();
            $this->nextRequiredSignalAlias = $this->firstSignalClass;
        }

        $this->trade = $this->actions = null;
    }

    /**
     * @param Candles  $candles
     * @param Signal[] $signals
     *
     * @return TradeSetup|null
     */
    public function findTradeWithSignals(Candles $candles, array $signals): ?TradeSetup
    {
        if (!$signals)
        {
            throw new \LogicException('$signals must not be empty.');
        }

        $this->sortByRequiredOrder($signals);

        foreach ($signals as $signal)
        {
            if (!$this->isRequiredNextSignal($signal) || !$this->verifySignal($signal))
            {
                return null;
            }

            $this->handleNewRequiredSignal($signal);

            if ($this->areRequirementsComplete())
            {
                return $this->runCallback($candles);
            }
        }

        return null;
    }

    public function findTrade(Candles $candles): ?TradeSetup
    {
        return $this->runCallback($candles);
    }

    protected function isRequiredNextSignal(Signal $signal): bool
    {
        return !$this->nextRequiredSignalAlias || $signal->indicator->alias === $this->nextRequiredSignalAlias;
    }

    protected function verifySignal(Signal $signal): bool
    {
        // multiple signals must be on the same side
        // signals must be in chronological order
        $lastSignal = $this->getLastSignal();
        if ($lastSignal && ($signal->timestamp < $lastSignal->timestamp || $lastSignal->side !== $signal->side))
        {
            return false;
        }

        // signals must pass the name condition if defined in the config
        $names = $this->config->signals[$signal->indicator->alias] ?? null;
        if ($names && !\in_array($signal->name, $names))
        {
            return false;
        }

        return true;
    }

    public function getLastSignal(): ?Signal
    {
        return $this->signals->last();
    }

    protected function handleNewRequiredSignal(Signal $signal): void
    {
        $this->signals[] = $signal;
        $this->nextRequiredSignalAlias = $this->signalOrderMap[$signal->indicator->alias] ?? null;
    }

    protected function areRequirementsComplete(): bool
    {
        return $this->signals->count() == $this->requiredSignalCount;
    }

    protected function setup(): TradeSetup
    {
        $setup = new TradeSetup();

        $setup->signature()->associate($this->config->signature);

        $setup->entry_order_type = OrderType::MARKET;

        if ($this->config->withSignals)
        {
            /** @var Signal $lastSignal */
            $lastSignal = $this->getLastSignal();

            $setup->name = $this->signals
                ->map(static fn(Signal $signal): string => $signal->name)
                ->implode('|');
            $setup->side = $lastSignal->side;
            $setup->timestamp = $lastSignal->timestamp;
            $setup->price = $lastSignal->price;
            $setup->price_date = $lastSignal->price_date;
            $setup->signal_count = $this->signals->count();
        }

        return $setup;
    }

    protected function runCallback(Candles $candles): ?TradeSetup
    {
        $this->trade = $this->setup();

        return ($this->config->setup)(trade: $this->trade, candles: $candles, signals: $this->signals);
    }

    /**
     * @param Signal[] $signals
     *
     * @return void
     */
    protected function sortByRequiredOrder(array &$signals): void
    {
        if (count($signals) <= 1)
        {
            return;
        }
        uasort($signals, function (Signal $a, Signal $b): int {
            return $this->signalOrder[$a->indicator->alias] <=> $this->signalOrder[$b->indicator->alias];
        });
    }

    private function getSignalOrder(array $signalOrderMap): array
    {
        $order = [];

        foreach ($signalOrderMap as $first => $next)
        {
            $order[] = $first;

            if ($next)
            {
                $order[] = $next;
            }
        }

        return array_flip($order);
    }
}