<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\Signature;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\CandleCollection;
use App\Trade\Candles;
use App\Trade\Config\IndicatorConfig;
use App\Trade\Config\TradeConfig;
use App\Trade\Evaluation\TradeLoop;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Indicator\Indicator;
use App\Trade\Log;
use App\Trade\Strategy\Action\Handler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

abstract class Strategy
{
    use HasName;
    use HasSignature;
    use HasConfig;

    protected array $config = [];
    protected \WeakMap $actions;
    protected SymbolRepository $symbolRepo;
    protected Symbol $evaluationSymbol;
    protected CandleCollection $candles;
    protected Symbol $symbol;
    /** @var IndicatorConfig[] */
    private array $indicatorConfig;
    private TradeConfig $tradeConfig;
    /**
     * @var TradeSetup[]
     */
    private Collection $trades;
    /**
     * @var Collection[]
     */
    private Collection $signals;
    /**
     * @var Indicator[]
     */
    private Collection $indicators;
    /**
     * @var Indicator[]
     */
    private Collection $helperIndicators;

    public function __construct(array $config = [])
    {
        $this->mergeConfig($config);

        $this->symbolRepo = App::make(SymbolRepository::class);

        $this->indicators = new Collection();
        $this->actions = new \WeakMap();
        $this->indicatorConfig = $this->newIndicatorConfig();
        $this->tradeConfig = $this->newTradeConfig();
        $this->signature = $this->register(['contents' => $this->contents()]);
    }

    /**
     * @return IndicatorConfig[]
     */
    private function newIndicatorConfig(): array
    {
        foreach ($config = $this->indicatorConfig() as $class => &$c)
        {
            $c['class'] = $class;
            $c = IndicatorConfig::fromArray($c);
        }

        return $config;
    }

    abstract protected function indicatorConfig(): array;

    private function newTradeConfig(): TradeConfig
    {
        $c = $this->tradeConfig();

        $c['symbol'] = $this->symbol;
        $c['signature'] = $this->getTradeConfigSignature($c);

        return TradeConfig::fromArray($c);
    }

    abstract protected function tradeConfig(): array;

    protected function getTradeConfigSignature(array $config): Signature
    {
        return $this->register(
            ['strategy'        => [
                'signature' => $this->signature->hash
            ],
             'trade_setup'     => $config,
             'indicator_setup' => array_map(
                 fn(string $class): array => $this->indicatorConfig[$class]->toArray(),
                 $this->getSignalClasses($config))
            ]);
    }

    /**
     * @return string[]
     */
    protected function getSignalClasses(array $config): array
    {
        $indicators = [];
        foreach ($config['signals'] as $key => $indicator)
        {
            $indicators[] = is_array($indicator) ? $key : $indicator;
        }

        return $indicators;
    }

    public function getFirstTrade(): ?TradeSetup
    {
        return $this->trades->first();
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

        $iterator = $this->trades->getIterator();

        while ($iterator->valid())
        {
            if ($iterator->key() == $timestamp)
            {
                $iterator->next();
                return $iterator->current();
            }

            $iterator->next();
        }

        return null;
    }

    public function newAction(TradeSetup $trade, string $actionClass, array $config): void
    {
        if (!is_subclass_of($actionClass, Handler::class))
        {
            throw new \InvalidArgumentException('Invalid trade action class: ' . $actionClass);
        }

        if (!isset($this->actions[$trade]))
        {
            $this->actions[$trade] = new Collection();
        }

        $this->actions[$trade][$actionClass] = $config;
    }

    public function signals(): Collection
    {
        return $this->signals;
    }

    public function run(Symbol $symbol): void
    {
        $this->symbol = $symbol;
        $this->symbol->updateCandles();

        $this->evaluationSymbol = $this->getEvaluationSymbol();
        $this->evaluationSymbol->updateCandlesIfOlderThan(60);

        Log::execTimeStart('populateCandles');
        $this->populateCandles();
        Log::execTimeStart('populateCandles');

        Log::execTimeStart('initIndicators');
        $this->initIndicators();
        Log::execTimeFinish('initIndicators');

        Log::execTimeStart('findTrades');
        $this->findTrades();
        Log::execTimeFinish('findTrades');
    }

    protected function getEvaluationSymbol(): Symbol
    {
        $exchange = $this->symbol->exchange();
        $symbolName = $this->symbol->symbol;
        $evaluationInterval = $this->config('evaluation.interval', true);
        return $this->symbolRepo->fetchSymbol($exchange, $symbolName, $evaluationInterval)
            ?? $this->symbolRepo->fetchSymbolFromExchange($exchange, $symbolName, $evaluationInterval);
    }

    protected function populateCandles(): void
    {
        $this->candles = $this->symbol->candles(limit: $this->config['maxCandles'],
            start: $this->config['startDate'],
            end: $this->config['endDate']);
    }

    protected function initIndicators(): void
    {
        $this->initHelperIndicators($this->symbol, $this->candles);

        foreach ($this->indicatorConfig as $class => $setup)
        {
            /** @var Indicator $indicator */
            $indicator = new $class(symbol: $this->symbol,
                candles: $this->candles,
                config: is_array($setup) ? $setup['config'] ?? [] : []);

            $this->indicators[$indicator->id()] = $indicator;
            $this->symbol->addIndicator(indicator: $indicator);
        }
    }

    protected function initHelperIndicators(Symbol $symbol, Collection $candles): void
    {
        $helpers = [];
        foreach ($this->helperIndicators() as $class => $config)
        {
            /** @var Symbol $helperSymbol */
            $helperSymbol = Symbol::query()
                ->where('exchange_id', $symbol->exchange_id)
                ->where('symbol', $config['symbol'] ?? $symbol->symbol)
                ->where('interval', $config['interval'] ?? $symbol->interval)
                ->firstOrFail();

            $helperSymbol->updateCandles();

            $nextCandle = $this->symbolRepo->fetchNextCandle($symbol->id, $candles->last()->t);
            $helperCandles = $helperSymbol->candles(start: $candles->first()->t, end: $nextCandle?->t);

            unset($config['interval'], $config['symbol']);

            /** @var Indicator $helperIndicator */
            $helperIndicator = new $class(symbol: $helperSymbol, candles: $helperCandles, config: $config);
            if ($helperIndicator->symbol() === $symbol)
            {
                $symbol->addIndicator($helperIndicator);
            }
            $helpers[$class] = $helperIndicator;
        }

        $this->helperIndicators = new Collection($helpers);
    }

    protected function helperIndicators(): array
    {
        return [];
    }

    protected function findTrades(): void
    {
        $candleIterator = $this->candles->getIterator();
        $candles = new Candles($candleIterator, $this->candles);
        $creator = new TradeCreator($this->tradeConfig);
        $hasSignal = !empty($this->tradeConfig->signals);

        /** @var Indicator[]|Collection $indicators */
        $indicators = $this->indicators
            ->filter(static fn(Indicator $indicator): bool => in_array($indicator::class, $creator->signalClasses))
            ->keyBy(static fn(Indicator $indicator): string => $indicator::class);

        /** @var \Generator[] $generators */
        $generators = $indicators->map(static fn(Indicator $indicator): \Generator => $indicator->scan(
            $this->indicatorConfig[$indicator::class]['signal']));

        $this->assertProgressiveness($indicators);

        while ($candleIterator->valid())
        {
            /** @var \stdClass $candle */
            $candle = $candleIterator->current();
            $key = $candleIterator->key();
            $candleIterator->next();
            $next = $candleIterator->current();
            $priceDate = $this->symbolRepo->getPriceDate($candle->t, $next?->t, $this->symbol);

            if ($hasSignal)
            {
                do
                {
                    foreach ($indicators as $class => $indicator)
                    {
                        $result = $generators[$class]->current();
                        $signal = $result['signal'];

                        $this->runUnderCandle($key, $indicator->candle(), function () use ($candles, $creator, $signal) {
                            if ($trade = $creator->findTrade($candles, $signal))
                            {
                                $creator->setActions($this->actions($trade));
                                $savedTrade = $creator->save();

                                foreach ($this->indicators as $i)
                                {
                                    $i->replaceBindable($trade, $savedTrade);
                                    $i->saveBindings($savedTrade);
                                }

                                $this->trades[$trade->timestamp] = $trade = $savedTrade;
                            }
                        });
                    }
                } while ($result['price_date'] <= $priceDate);
            }
            else
            {
                //TODO:: handle no signal case
            }
        }
    }

    protected function assertProgressiveness(array $indicators): void
    {
        $isProgressive = null;
        /** @var Indicator $i */
        foreach ($indicators as $i)
        {
            if ($isProgressive === null)
            {
                $isProgressive = $i->isProgressive();
            }
            else if ($i->isProgressive() !== $isProgressive)
            {
                throw new \LogicException('All indicators must be either progressive or non-progressive');
            }
        }
    }

    protected function runUnderCandle(int $key, \stdClass $candle, \Closure $closure): void
    {
        $this->candles->overrideCandle($key, $candle);
        try
        {
            $closure();
        } finally
        {
            $this->candles->forgetOverride($key);
        }
    }

    public function actions(TradeSetup $setup): ?Collection
    {
        return $this->actions[$setup] ?? null;
    }

    public function newLoop(TradeSetup $entry): TradeLoop
    {
        return new TradeLoop($entry, $this->evaluationSymbol, $this->config('evaluation.loop'));
    }

    public function trades(): Collection
    {
        return $this->trades;
    }

    public function helperIndicator(string $class): Indicator
    {
        return $this->helperIndicators[$class];
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

    protected function indicator(Signal $signal): Indicator
    {
        return $this->indicators[$signal->indicator_id]
            ?? throw new \InvalidArgumentException('Signal indicator was not found.');
    }

    final protected function getDefaultConfig(): array
    {
        return [
            'maxCandles'   => 1000,
            'startDate'    => null,
            'endDate'      => null,
            //when true, multiple trades to the same direction will be disregarded
            'oppositeOnly' => false,
            'evaluation'   => [
                'loop'     => [
                    //trade duration in minutes, 0 to disable
                    //exceeding trades will be stopped at close price
                    'timeout'     => 0,
                    //when true, close trade immediately at exit setup
                    'closeOnExit' => true,
                ],
                'interval' => '1m'
            ],
            //trade commission cut, each trade costs two fees
            'feeRatio'     => 0.001
        ];
    }
}