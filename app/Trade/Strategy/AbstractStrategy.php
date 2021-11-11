<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\Binding;
use App\Models\Signal;
use App\Models\Signature;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Binding\CanBind;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Indicator\AbstractIndicator;
use App\Trade\Log;
use App\Trade\Strategy\TradeAction\AbstractTradeActionHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

abstract class AbstractStrategy
{
    use HasName;
    use HasSignature;
    use HasConfig;
    use CanBind;

    protected array $config = [];

    protected \WeakMap $actions;

    private array $indicatorSetup;
    private array $tradeSetup;
    private ?Signal $lastSignal;
    private array $bindMap;
    /**
     * @var TradeSetup[]
     */
    private Collection $trades;
    /**
     * @var Collection[]
     */
    private Collection $signals;
    /**
     * @var AbstractIndicator[]
     */
    private Collection $indicators;

    public function __construct(array $config = [])
    {
        $this->mergeConfig($config);

        $this->indicators = new Collection();
        $this->indicatorSetup = $this->indicatorSetup();
        $this->tradeSetup = $this->tradeSetup();
        $this->signature = $this->register([
            'contents' => $this->contents()
        ]);

        $this->bindMap = $this->getBindMap();

        $this->actions = new \WeakMap();
    }

    public function newAction(TradeSetup $setup, string $actionClass, array $config)
    {
        if (!is_subclass_of($actionClass, AbstractTradeActionHandler::class))
        {
            throw new \InvalidArgumentException('Invalid trade action class: ' . $actionClass);
        }

        if (!isset($this->actions[$setup]))
        {
            $this->actions[$setup] = new Collection();
        }

        $this->actions[$setup][$actionClass] = $config;
    }

    public function actions(TradeSetup $setup): ?Collection
    {
        return $this->actions[$setup] ?? null;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'maxCandles' => 1000,
            'startDate'  => null,
            'endDate'    => null
        ];
    }

    abstract protected function indicatorSetup(): array;

    abstract protected function tradeSetup(): array;

    protected function getBindMap(): array
    {
        return ['last_signal_price' => 'price'];
    }

    public function trades(): Collection
    {
        return $this->trades;
    }

    public function signals(): Collection
    {
        return $this->signals;
    }

    public function run(Symbol $symbol): void
    {
        Log::execTimeStart('CandleUpdater::update()');
        $symbol->exchange()->updater()->update($symbol);
        Log::execTimeFinish('CandleUpdater::update()');

        Log::execTimeStart('AbstractStrategy::initIndicators()');
        $this->initIndicators($symbol);
        Log::execTimeFinish('AbstractStrategy::initIndicators()');

        $this->signals = $this->getConfigSignals($symbol);

        Log::execTimeStart('AbstractStrategy::findTrades()');
        $this->trades = $this->findTrades($symbol);
        Log::execTimeFinish('AbstractStrategy::findTrades()');
    }

    protected function initIndicators(Symbol $symbol): void
    {
        $candles = $symbol->candles(limit: $this->config['maxCandles'],
            start: $this->config['startDate'],
            end: $this->config['endDate']);

        foreach ($this->indicatorSetup as $class => $setup)
        {
            /** @var AbstractIndicator $indicator */
            $indicator = new $class(symbol: $symbol,
                candles: $candles,
                config: is_array($setup) ? $setup['config'] ?? [] : [],
                signalCallback: $setup instanceof \Closure ? $setup : $setup['signal'] ?? null);

            $this->indicators[$indicator->id()] = $indicator;
            $symbol->addIndicator(indicator: $indicator);
        }
    }

    protected function getConfigSignals(Symbol $symbol): Collection
    {
        $signals = [];
        foreach ($this->tradeSetup as $config)
        {
            /* @var \App\Trade\Indicator\AbstractIndicator $indicator */
            foreach ($this->getConfigIndicators($config) as $indicator)
            {
                $indicator = $symbol->indicator($indicator::name());
                $signals[$indicator::class] = $indicator->signals();
            }
        }

        return new Collection($signals);
    }

    /**
     * @return string[]
     */
    protected function getConfigIndicators(array $config): array
    {
        $indicators = [];
        foreach ($config['signals'] as $key => $indicator)
        {
            $indicators[] = is_array($indicator) ? $key : $indicator;
        }

        return $indicators;
    }

    /**
     * @return TradeSetup[]
     * @throws \Exception
     */
    protected function findTrades(Symbol $symbol): Collection
    {
        $setups = [];

        foreach ($this->tradeSetup as $key => $config)
        {
            if (!$indicators = $this->getConfigIndicators($config))
            {
                throw new \UnexpectedValueException('Invalid signal config for trade setup: ' . $key);
            }

            $required = count($config['signals']);
            $signals = [];
            $firstIndicator = $indicators[0];
            $index = 0;

            while (isset($this->signals[$firstIndicator][$index]))
            {
                foreach ($indicators as $k => $indicator)
                {
                    $isFirst = $k === 0;
                    /* @var Signal $signal */
                    foreach ($this->signals[$indicator] as $i => $signal)
                    {
                        if ($isFirst)
                        {
                            if ($i < $index) continue;
                            $index = $i + 1;
                        }

                        /** @var Signal $lastSignal */
                        $lastSignal = end($signals);

                        if (!$lastSignal || ($signal->timestamp >= $lastSignal->timestamp && $lastSignal->side === $signal->side))
                        {
                            if ($this->validateSignal($indicator, $config, $signal))
                            {
                                $signals[] = $signal;
                                break;
                            }
                        }
                    }
                }

                if ($required === count($signals))
                {
                    $this->lastSignal = $signal;
                    $signals = new Collection($signals);
                    $tradeSetup = $this->setupTrade($symbol, $config, $signals);

                    if ($tradeSetup = $this->applyTradeSetupConfig($tradeSetup, $signals, $config))
                    {
                        $setups[$key] = $setups[$key] ?? new Collection();
                        $setups[$key][$tradeSetup->timestamp] = $this->saveTrade($tradeSetup, $signals);
                    }
                    $this->lastSignal = null;
                }

                $signals = [];
            }
        }

        return new Collection($setups);
    }

    protected function validateSignal(string $indicator, array $config, Signal $signal): bool
    {
        return !is_array($config['signals'][$indicator] ?? null) ||
            in_array($signal->name, $config['signals'][$indicator]);
    }

    /**
     * @param Signal[] $signals
     */
    protected function setupTrade(Symbol $symbol, array $config, Collection $signals): TradeSetup
    {
        $signature = $this->registerTradeSetupSignature($config);
        $tradeSetup = $this->newTrade($symbol, $signature);

        return $this->fillTrade($tradeSetup, $signals);
    }

    protected function registerTradeSetupSignature(array $config): Signature
    {
        return $this->register([
            'strategy'        => [
                'signature' => $this->signature->hash
            ],
            'trade_setup'     => $config,
            'indicator_setup' => array_map(
                fn(string $class): array => $this->indicatorSetup[$class],
                $this->getConfigIndicators($config))
        ]);
    }

    protected function newTrade(Symbol $symbol, Signature $signature): TradeSetup
    {
        $tradeSetup = new TradeSetup();

        $tradeSetup->symbol()->associate($symbol);
        $tradeSetup->signature()->associate($signature);

        return $tradeSetup;
    }

    protected function fillTrade(TradeSetup $tradeSetup, Collection $signals): TradeSetup
    {
        /** @var Signal $lastSignal */
        $lastSignal = $signals->last();

        $tradeSetup->signal_count = count($signals);
        $tradeSetup->name = $signals->map(static fn(Signal $signal): string => $signal->name)->implode('|');
        $tradeSetup->side = $lastSignal->side;
        $tradeSetup->timestamp = $lastSignal->timestamp;
        $tradeSetup->price = $lastSignal->price;

        return $tradeSetup;
    }

    protected function applyTradeSetupConfig(TradeSetup $tradeSetup, Collection $signals, mixed $config): ?TradeSetup
    {
        $callback = $config['callback'] ?? null;

        if ($callback instanceof \Closure)
        {
            $tradeSetup = $callback($tradeSetup, $signals);
        }

        return $tradeSetup;
    }

    /**
     * @throws \Exception
     */
    protected function saveTrade(TradeSetup $tradeSetup, Collection $signals): TradeSetup
    {
        $old = $tradeSetup;
        $actions = $this->actions($old);
        DB::transaction(function () use (&$tradeSetup, &$signals, &$actions) {

            /** @var TradeSetup $tradeSetup */
            $tradeSetup = $tradeSetup->updateUniqueOrCreate();
            $tradeSetup->actions()->delete();

            if ($actions)
            {
                foreach ($actions as $class => $config)
                {
                    $tradeSetup->actions()->create([
                        'class'  => $class,
                        'config' => $config
                    ]);
                }
            }

            $tradeSetup->signals()->sync($signals->map(static fn(Signal $signal): int => $signal->id)->all());
        });

        $this->replaceBindable($old, $tradeSetup);
        $this->saveBindings($tradeSetup);

        foreach ($this->indicators as $indicator)
        {
            $indicator->replaceBindable($old, $tradeSetup);
            $indicator->saveBindings($tradeSetup);
        }

        return $tradeSetup;
    }

    public function indicator(int $id): AbstractIndicator
    {
        return $this->indicators[$id];
    }

    protected function getSavePoints(string|int $bind, Signature $signature): array
    {
        $data = $signature->data;

        if ($bind === 'last_signal_price' && ($id = $data['extra']['last_signal_binding_signature_id']))
        {
            return DB::table('save_points')
                ->where('binding_signature_id', $id)
                ->where('timestamp', '>=', $this->config['startDate'])
                ->where('timestamp', '<=', $this->config['endDate'])
                ->get(['timestamp', 'value'])
                ->map(fn($v) => (array)$v)
                ->all();
        }

        throw new \InvalidArgumentException("Could not get save points for bind: {$bind}");
    }

    protected function getBindable(): array
    {
        return ['last_signal_price'];
    }

    protected function getBindingSignatureExtra(string|int $bind): array
    {
        return [
            'last_signal_binding_signature_id' => $this->fetchSignalBindingSignature($this->lastSignal,
                $this->bindMap['last_signal_price'])->id
        ];
    }

    private function fetchSignalBindingSignature(Signal $signal, string $column): Signature
    {
        /** @var Binding $binding */
        $binding = $signal->bindings()
            ->with('signature')
            ->where('column', $column)
            ->first();

        return $binding->signature;
    }

    protected function getBindValue(int|string $bind, ?int $timestamp = null): mixed
    {
        $column = $this->bindMap[$bind] ?? $bind;

        return $this->lastSignal->getAttribute($column);
    }
}