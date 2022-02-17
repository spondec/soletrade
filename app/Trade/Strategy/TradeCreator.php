<?php

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Candles;
use App\Trade\Config\TradeConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TradeCreator
{
    public readonly array $signalClasses;
    public readonly array $signalOrderMap;
    public readonly string $firstSignalClass;
    public readonly int $requiredSignalCount;

    protected ?Collection $actions = null;
    protected ?TradeSetup $trade = null;
    protected ?string $requiredNextSignal = null;
    protected array $signals = [];

    public function __construct(public TradeConfig $config)
    {
        $this->signalClasses = $config->getSignalClasses();
        $this->requiredSignalCount = \count($this->signalClasses);
        if ($this->requiredSignalCount)
        {
            $this->signalOrderMap = $this->getSignalOrderMap();
            $this->firstSignalClass = $this->requiredNextSignal = \array_key_first($this->signalOrderMap);
        }
    }

    protected function getSignalOrderMap(): array
    {
        $signalMap = [];
        $iterator = new \ArrayIterator($this->signalClasses);

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


        DB::transaction(function () use (&$tradeSetup) {

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
                ->sync(\array_map(static fn(Signal $signal): int => $signal->id, $this->signals));
        });

        $this->finalize();

        return $tradeSetup;
    }

    protected function finalize(): void
    {
        if ($this->requiredSignalCount)
        {
            $this->signals = [];
            $this->requiredNextSignal = $this->firstSignalClass;
        }

        $this->trade = $this->actions = null;
    }

    public function findTradeWithSignal(Candles $candles, ?Signal $signal = null): ?TradeSetup
    {
        if (!$signal && !$this->requiredSignalCount)
        {
            //TODO:: handle no signal setups
            return null;
        }

        if ($signal && $this->isRequiredNextSignal($signal) && $this->verifySignal($signal))
        {
            $this->handleNewRequiredSignal($signal);

            if ($this->areRequirementsComplete())
            {
                if ($this->trade)
                {
                    if ($this->trade->isDirty())
                    {
                        throw new \UnexpectedValueException('Incomplete trades must not be modified.');
                    }
                }
                else
                {
                    $this->trade = $this->setup();
                }

                return $this->runCallback($candles);
            }
        }

        return null;
    }

    protected function isRequiredNextSignal(Signal $signal): bool
    {
        return !$this->requiredNextSignal || $signal->indicator::class === $this->requiredNextSignal;
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
        $names = $this->config->signals[$signal->indicator::class] ?? null;
        if ($names && !\in_array($signal->name, $names))
        {
            return false;
        }

        return true;
    }

    public function getLastSignal(): bool|Signal
    {
        return \end($this->signals);
    }

    protected function handleNewRequiredSignal(Signal $signal): void
    {
        $this->signals[] = $signal;
        $this->requiredNextSignal = $this->signalOrderMap[$signal->indicator::class] ?? null;
    }

    protected function areRequirementsComplete(): bool
    {
        return \count($this->signals) == $this->requiredSignalCount;
    }

    protected function setup(): TradeSetup
    {
        $tradeSetup = new TradeSetup();

        $tradeSetup->signature()->associate($this->config->signature);

        /** @var Signal $lastSignal */
        $lastSignal = $this->getLastSignal();

        $tradeSetup->signal_count = \count($this->signals);
        $tradeSetup->name = \implode('|',
            \array_map(static fn(Signal $signal): string => $signal->name, $this->signals));
        $tradeSetup->side = $lastSignal->side;
        $tradeSetup->timestamp = $lastSignal->timestamp;
        $tradeSetup->price = $lastSignal->price;
        $tradeSetup->price_date = $lastSignal->price_date;

        return $tradeSetup;
    }

    protected function runCallback(Candles $candles): ?TradeSetup
    {
        return ($this->config->callback)(trade: $this->trade, candles: $candles, signals: collect($this->signals));
    }
}