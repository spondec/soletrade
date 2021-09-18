<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\Binding;
use App\Models\Signal;
use App\Models\Signature;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Binding\Bindable;
use App\Trade\Binding\CanBind;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Indicator\AbstractIndicator;
use App\Trade\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

abstract class AbstractStrategy
{
    use HasName;
    use HasSignature;
    use HasConfig;
    use CanBind;

    protected array $config = [
        'maxCandles' => 1000,
        'startDate'  => null,
        'endDate'    => null
    ];

    protected array $signals = [];
    protected array $indicatorSetup;
    protected array $tradeSetup;
    protected SymbolRepository $symbolRepo;

    protected ?Signal $lastSignal;

    public function __construct(array $config = [])
    {
        $this->mergeConfig($config);

        $this->symbolRepo = App::make(SymbolRepository::class);
        $this->indicatorSetup = $this->indicatorSetup();
        $this->tradeSetup = $this->tradeSetup();
        $this->signature = $this->register([
            'contents' => $this->contents()
        ]);
    }

    abstract protected function indicatorSetup(): array;

    abstract protected function tradeSetup(): array;

    public function run(Symbol $symbol): Collection
    {
        Log::execTime(function () use (&$symbol) {
            $this->initIndicators($symbol);
        }, 'AbstractStrategy::initIndicators()');

        Log::execTime(function () use (&$trades, &$symbol) {
            $trades = $this->findTradeSetups($symbol);
        }, 'AbstractStrategy::findTrades()');

        return $trades;
    }

    protected function initIndicators(Symbol $symbol): void
    {
        $candles = $symbol->candles(limit: $this->config['maxCandles'],
            start: $this->config['startDate'],
            end: $this->config['endDate']);

        foreach ($this->indicatorSetup as $class => $setup)
        {
            $indicator = new $class(symbol: $symbol,
                candles: $candles,
                config: is_array($setup) ? $setup['config'] ?? [] : [],
                signalCallback: $setup instanceof \Closure ? $setup : $setup['signal'] ?? null);

            $symbol->addIndicator(indicator: $indicator);
        }
    }

    /**
     * @return TradeSetup[]
     * @throws \Exception
     */
    protected function findTradeSetups(Symbol $symbol): Collection
    {
        if (!$signals = $this->getConfigSignals($symbol))
        {
            return [];
        }

        $setups = [];

        foreach ($this->tradeSetup as $key => $config)
        {
            if (!$indicators = $this->getConfigIndicators($config))
            {
                throw new \UnexpectedValueException('Invalid signal config for trade setup: ' . $key);
            }

            $requiredTotal = count($config['signals']);
            $requiredSignals = [];
            $firstIndicator = $indicators[0];
            $index = 0;

            while (isset($signals[$firstIndicator][$index]))
            {
                foreach ($indicators as $k => $indicator)
                {
                    $isFirst = $k === 0;
                    /* @var Signal $signal */
                    foreach ($signals[$indicator] as $i => $signal)
                    {
                        if ($isFirst)
                        {
                            if ($i < $index)
                            {
                                continue;
                            }

                            $index = $i + 1;
                        }

                        /** @var Signal $lastSignal */
                        $lastSignal = end($requiredSignals);

                        if (!$lastSignal || ($signal->timestamp >= $lastSignal->timestamp && $lastSignal->side === $signal->side))
                        {
                            if ($this->validateSignal($indicator, $config, $signal))
                            {
                                $requiredSignals[] = $signal;
                                break;
                            }
                        }
                    }
                }

                if ($requiredTotal == count($requiredSignals))
                {
                    $this->lastSignal = $signal;
                    $tradeSetup = $this->setupTrade($symbol, $config, $requiredSignals);

                    if ($tradeSetup = $this->applyTradeSetupConfig($tradeSetup, $config))
                    {
                        $setups[$key] = $setups[$key] ?? new Collection();
                        $setups[$key][$tradeSetup->timestamp] = $this->saveTrade($tradeSetup, $requiredSignals);
                    }
                    $this->lastSignal = null;
                }

                $requiredSignals = [];
            }
        }

        return new Collection($setups);
    }

    protected function getConfigSignals(Symbol $symbol): array
    {
        $signals = [];
        foreach ($this->tradeSetup as $config)
        {
            /* @var AbstractIndicator $indicator */
            foreach ($this->getConfigIndicators($config) as $indicator)
            {
                $indicator = $symbol->indicator($indicator::name());
                $signals[$indicator::class] = $indicator->signals();
            }
        }

        return $signals;
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

    protected function validateSignal(string $indicator, array $config, Signal $signal): bool
    {
        return !is_array($config['signals'][$indicator] ?? null) ||
            in_array($signal->name, $config['signals'][$indicator]);
    }

    /**
     * @param Signal[] $signals
     */
    protected function setupTrade(Symbol $symbol, array $config, array $signals): TradeSetup
    {
        $signature = $this->registerSignature($config);
        $tradeSetup = $this->createTradeSetup($symbol, $signature);

        return $this->fillTradeSetup($tradeSetup, $signals);
    }

    protected function registerSignature(array $config): Signature
    {
        $signature = $this->register([
            'strategy'        => [
                'config' => $this->config,
                'hash'   => $this->signature->hash
            ],
            'trade_setup'     => $config,
            'indicator_setup' => array_map(
                fn(string $class): array => $this->indicatorSetup[$class],
                $this->getConfigIndicators($config))
        ]);
        return $signature;
    }

    protected function createTradeSetup(Symbol $symbol, Signature $signature): TradeSetup
    {
        $tradeSetup = new TradeSetup();

        $tradeSetup->symbol()->associate($symbol);
        $tradeSetup->signature()->associate($signature);

        return $tradeSetup;
    }

    protected function fillTradeSetup(TradeSetup $tradeSetup, array $signals): TradeSetup
    {
        $lastSignal = end($signals);

        $tradeSetup->signal_count = count($signals);
        $tradeSetup->name = implode('|', array_map(
            static fn(Signal $signal) => $signal->name, $signals));
        $tradeSetup->side = $lastSignal->side;
        $tradeSetup->timestamp = $lastSignal->timestamp;

        $this->bind($tradeSetup, 'price', 'last_signal_price');

        return $tradeSetup;
    }

    protected function applyTradeSetupConfig(TradeSetup $tradeSetup, mixed $config): ?TradeSetup
    {
        $callback = $config['callback'] ?? null;

        if ($callback instanceof \Closure)
        {
            $tradeSetup = $callback($tradeSetup);
        }

        return $tradeSetup;
    }

    /**
     * @throws \Exception
     */
    protected function saveTrade(TradeSetup $tradeSetup, array $signals): TradeSetup
    {
        $old = $tradeSetup;
        DB::transaction(static function () use (&$tradeSetup, &$signals) {
            /** @var TradeSetup $tradeSetup */
            $tradeSetup = $tradeSetup->updateUniqueOrCreate();
            $tradeSetup->signals()->sync(array_map(static fn(Signal $signal) => $signal->id, $signals));
        });
        $this->replaceBindable($old, $tradeSetup);
        $this->saveBindings($tradeSetup);

        return $tradeSetup;
    }

    protected function preBindingSave(Binding $binding, ?\Closure $callback): void
    {
        $this->setHistory($binding,
            $this->getBindHistory($binding->name),
            $callback);
    }

    protected function getBindHistory(string $bind): ?array
    {
        $map = $this->getBindMap();
        return $this->lastSignal->bindings()->where('column', $map[$bind])->first()?->history;
    }

    protected function getBindMap(): array
    {
        return [
            'last_signal_price' => 'price'
        ];
    }

    protected function getBindable(): array
    {
        return ['last_signal_price'];
    }

    protected function getBindValue(int|string $bind): mixed
    {
        $map = $this->getBindMap();
        $column = $map[$bind] ?? $bind;

        return $this->lastSignal->getAttribute($column);
    }
}