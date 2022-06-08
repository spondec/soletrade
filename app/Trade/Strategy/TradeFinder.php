<?php

namespace App\Trade\Strategy;

use App\Models\TradeSetup;
use App\Trade\Candles;
use App\Trade\Collection\CandleCollection;
use App\Trade\Collection\TradeCollection;
use App\Trade\Config\IndicatorConfig;
use App\Trade\Config\TradeConfig;
use App\Trade\Indicator\Indicator;
use App\Trade\Repository\SymbolRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class TradeFinder
{
    protected \Iterator $candleIterator;
    protected Candles $_candles;

    protected TradeCreator $creator;

    /** @var \Generator[] */
    protected Collection $indicatorGenerators;

    protected SymbolRepository $symbolRepo;

    protected \Closure $hasNextCandle;

    /**
     * @var TradeSetup[]
     */
    protected array $trades = [];

    /**
     * @param  IndicatorConfig[]  $indicatorConfig
     * @param  Indicator[]  $indicators
     */
    public function __construct(protected Strategy $strategy,
                                protected CandleCollection $candles,
                                protected TradeConfig $tradeConfig,
                                protected Collection $indicatorConfig,
                                protected Collection $indicators)
    {
        $this->candleIterator = $this->candles->getIterator();
        $this->_candles = new Candles($this->candleIterator, $this->candles, $this->strategy->symbol());
        $this->creator = new TradeCreator($this->tradeConfig);
        $this->symbolRepo = App::make(SymbolRepository::class);

        $this->indicators = $this->indicators->filter(fn (Indicator $i): bool => $i->hasData());
        $this->indicatorGenerators = $this->getIndicatorGenerators();
        $this->initGenerators($this->indicatorGenerators);

        $this->hasNextCandle = \Closure::bind(function (): bool
        {
            return isset($this->candles[$this->iterator->key() + 1]);
        },
            $this->_candles,
            Candles::class
        );
    }

    /**
     * @return \Generator[]
     */
    private function getIndicatorGenerators(): Collection
    {
        return $this->indicators->mapWithKeys(
            function (Indicator $indicator, string $alias)
            {
                return [
                    $alias => $indicator->scan(\in_array($alias, $this->tradeConfig->signals)
                        ? $this->indicatorConfig[$alias]->signal
                        : null),
                ];
            }
        );
    }

    /**
     * @param  \Generator[]  $generators
     * @return void
     */
    private function initGenerators(\Traversable $generators): void
    {
        foreach ($generators as $generator)
        {
            $generator->current();
        }
    }

    /**
     * @return TradeCollection<TradeSetup>
     */
    public function findTrades(): TradeCollection
    {
        while ($this->candleIterator->valid())
        {
            /** @var \stdClass $candle */
            $candle = $this->candleIterator->current();
            $key = $this->candleIterator->key();
            $results = $this->getIndicatorGeneratorResults($candle, $indicator) ?? [];

            if ($this->tradeConfig->withSignals)
            {
                if ($signals = $this->extractSignals($results))
                {
                    $this->runUnderCandle($key, $indicator->candle(), function () use (&$signals)
                    {
                        if ($trade = $this->creator->findTradeWithSignals($this->_candles, $signals))
                        {
                            $this->saveTrade($trade);
                        }
                    });
                }
            }
            else
            {
                $this->runUnderCandle($key, $candle, function ()
                {
                    if ($trade = $this->creator->findTrade($this->_candles))
                    {
                        $this->saveTrade($trade);
                    }
                });
            }

            $this->candleIterator->next();
        }

        return new TradeCollection($this->trades, $this->strategy->config('trades'));
    }

    protected function getIndicatorGeneratorResults(\stdClass $candle, ?Indicator &$indicator = null): ?array
    {
        $results = [];
        foreach ($this->indicatorGenerators as $alias => $generator)
        {
            $indicator = $this->indicators[$alias];

            $indicatorCandle = $indicator->candle();

            if ($indicatorCandle->t < $candle->t)
            {
                while ($indicator->candle()->t < $candle->t)
                {
                    $generator->next();

                    if ($indicator->candle()->t == $candle->t)
                    {
                        $results[] = $generator->current();
                    }
                }
            }
            elseif ($indicatorCandle->t == $candle->t)
            {
                $results[] = $generator->current();
            }
            else
            {
                //TODO:: indicator is in the future?
            }
        }

        \uasort($results, fn (array $a, array $b): int => $a['price_date'] <=> $b['price_date']);

        return $results;
    }

    protected function runUnderCandle(int $key, \stdClass $candle, \Closure $closure): void
    {
        $this->candles->overrideCandle($key, $candle);
        try
        {
            $closure();
        }
        finally
        {
            $this->candles->forgetOverride($key);
        }
    }

    protected function saveTrade(TradeSetup $trade): void
    {
        if ($actions = $this->strategy->actions($trade))
        {
            $this->creator->setActions($actions);
        }
        $this->creator->setSymbol($this->strategy->symbol());

        $trade->is_permanent = ($this->hasNextCandle)();

        $savedTrade = $this->creator->save();

        foreach ($this->indicators as $i)
        {
            $i->replaceBindable($trade, $savedTrade);
            $i->saveBindings($savedTrade);
        }

        $this->trades[$savedTrade->timestamp] = $savedTrade;
    }

    protected function extractSignals(array $results): array
    {
        $signals = [];
        foreach ($results as $result)
        {
            if ($signal = $result['signal'] ?? null)
            {
                $signals[] = $signal;
            }
        }

        return $signals;
    }
}
