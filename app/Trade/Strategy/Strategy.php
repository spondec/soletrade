<?php

declare(strict_types=1);

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\Signature;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Action\Handler;
use App\Trade\Collection\CandleCollection;
use App\Trade\Collection\TradeCollection;
use App\Trade\Config\IndicatorConfig;
use App\Trade\Config\TradeConfig;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Indicator\Indicator;
use App\Trade\Log;
use App\Trade\Strategy\Finder\TradeFinder;
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
    public readonly Symbol $evaluationSymbol;
    protected CandleCollection $candles;
    protected Symbol $symbol;
    /** @var IndicatorConfig[] */
    private array $indicatorConfig;
    private TradeConfig $tradeConfig;
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
        $this->signature = $this->register(['contents' => $this->contents()]);

        $this->symbolRepo = App::make(SymbolRepository::class);

        $this->indicators = new Collection();
        $this->actions = new \WeakMap();
        $this->indicatorConfig = $this->newIndicatorConfig();
        $this->tradeConfig = $this->newTradeConfig();
    }

    /**
     * @return IndicatorConfig[]
     */
    private function newIndicatorConfig(): array
    {
        foreach ($config = $this->indicatorConfig() as $class => &$c)
        {
            $c['class'] = $class;
            $c['config'] = $c['config'] ?? [];
            $config[$class] = IndicatorConfig::fromArray($c);
        }

        return $config;
    }

    abstract protected function indicatorConfig(): array;

    private function newTradeConfig(): TradeConfig
    {
        $c = $this->tradeConfig();
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
             'indicator_setup' => \array_map(
                 static fn(IndicatorConfig $i): array => $i->toArray(),
                 $this->indicatorConfig)
            ]);
    }

    public function newAction(TradeSetup $trade, string $actionClass, array $config): void
    {
        if (!\is_subclass_of($actionClass, Handler::class))
        {
            throw new \InvalidArgumentException('Invalid trade action class: ' . $actionClass);
        }

        if (!isset($this->actions[$trade]))
        {
            $this->actions[$trade] = new Collection();
        }

        $this->actions[$trade][$actionClass] = $config;
    }

    public function run(Symbol $symbol): TradeCollection
    {
        $this->symbol = $symbol;
        $this->symbol->updateCandles();

        $this->evaluationSymbol = $this->getEvaluationSymbol();
        $this->evaluationSymbol->updateCandlesIfOlderThan(60);

        Log::execTimeStart('populateCandles');
        $this->populateCandles();
        Log::execTimeFinish('populateCandles');

        Log::execTimeStart('initIndicators');
        $this->initIndicators();
        Log::execTimeFinish('initIndicators');

        Log::execTimeStart('findTrades');
        $finder = new TradeFinder($this,
            $this->candles,
            $this->tradeConfig,
            collect($this->indicatorConfig),
            $this->indicators);
        $trades = $finder->findTrades();
        Log::execTimeFinish('findTrades');

        return $trades;
    }

    protected function getEvaluationSymbol(): Symbol
    {
        $exchange = $this->symbol->exchange();
        $symbolName = $this->symbol->symbol;

        if (!$evaluationInterval = $this->config('evaluation.interval'))
        {
            return $this->symbol;
        }

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
                config: \is_array($setup) ? $setup['config'] ?? [] : []);

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

    public function symbol(): Symbol
    {
        return $this->symbol;
    }

    public function actions(TradeSetup $setup): ?Collection
    {
        return $this->actions[$setup] ?? null;
    }

    public function helperIndicator(string $class): Indicator
    {
        return $this->helperIndicators[$class];
    }

    protected function indicator(Signal $signal): Indicator
    {
        return $this->indicators[$signal->indicator_id]
            ?? throw new \InvalidArgumentException('Signal indicator was not found.');
    }

    final protected function getDefaultConfig(): array
    {
        return [
            'maxCandles' => 1000,
            'startDate'  => null,
            'endDate'    => null,

            'trades'     => [
                //when true, multiple trades to the same direction will be disregarded
                'oppositeOnly' => false,
            ],
            'evaluation' => [
                'loop'     => [
                    //trade duration in minutes, 0 to disable
                    //exceeding trades will be stopped at close price
                    'timeout'     => 0,
                    //when true, close trade immediately at reverse(exit) setup
                    'closeOnExit' => true,
                ],
                'interval' => null
            ],
            //trade commission cut, each trade costs two fees
            'feeRatio'   => 0.001
        ];
    }
}