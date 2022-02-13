<?php

namespace App\Trade\Strategy\Finder;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\CandleCollection;
use App\Trade\Candles;
use App\Trade\Config\IndicatorConfig;
use App\Trade\Config\TradeConfig;
use App\Trade\Indicator\Indicator;
use App\Trade\Strategy\Strategy;
use App\Trade\Strategy\TradeCreator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class TradeFinder
{
    protected \Iterator $candleIterator;
    protected Candles $_candles;

    protected TradeCreator $creator;

    /** @var Indicator[] */
    protected Collection $signalIndicators;

    /** @var \Generator[] */
    protected Collection $signalGenerators;

    protected SymbolRepository $symbolRepo;

    /**
     * @param IndicatorConfig[] $indicatorConfig
     * @param Indicator[]       $indicators
     */
    public function __construct(protected Strategy         $strategy,
                                protected CandleCollection $candles,
                                protected TradeConfig      $tradeConfig,
                                protected Collection       $indicatorConfig,
                                protected Collection       $indicators)
    {
        $this->candleIterator = $this->candles->getIterator();
        $this->_candles = new Candles($this->candleIterator, $this->candles);
        $this->creator = new TradeCreator($this->tradeConfig);
        $this->symbolRepo = App::make(SymbolRepository::class);

        $this->signalIndicators = $this->getSignalIndicators();
        $this->signalGenerators = $this->getSignalGenerators();
        $this->initGenerators($this->signalGenerators);

        $this->assertProgressiveness($indicators);
    }

    /**
     * @return Indicator[]
     */
    private function getSignalIndicators(): Collection
    {
        return $this->indicators
            ->filter(fn(Indicator $indicator): bool => \in_array($indicator::class,
                $this->creator->signalClasses))
            ->keyBy(static fn(Indicator $indicator): string => $indicator::class);
    }

    /**
     * @return \Generator[]
     */
    private function getSignalGenerators(): Collection
    {
        return $this->signalIndicators->map(
            fn(Indicator $indicator): \Generator => $indicator->scan(
                $this->indicatorConfig[$indicator::class]->signal));
    }

    private function initGenerators(\Traversable $generators): void
    {
        foreach ($generators as $generator)
        {
            $generator->current();
        }
    }

    private function assertProgressiveness(Collection $indicators): void
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

    /**
     * @return Collection|TradeSetup[]
     */
    public function findTrades(): Collection
    {
        $trades = [];
        while ($this->candleIterator->valid())
        {
            /** @var \stdClass $candle */
            $candle = $this->candleIterator->current();
            $key = $this->candleIterator->key();
            $this->candleIterator->next();
//            $next = $this->candleIterator->current();

            if ($this->tradeConfig->withSignals)
            {
                if (!$result = $this->getSignalGeneratorResult($candle, $indicator))
                {
                    continue;
                }

                /** @var Signal $signal */
                $signal = $result['signal'];

                $this->runUnderCandle($key, $indicator->candle(), function () use (&$trades, &$signal) {
                    if ($trade = $this->creator->findTradeWithSignal($this->_candles, $signal))
                    {
                        if ($actions = $this->strategy->actions($trade))
                        {
                            $this->creator->setActions($actions);
                        }
                        $this->creator->setSymbol($this->strategy->symbol());
                        $savedTrade = $this->creator->save();

                        foreach ($this->indicators as $i)
                        {
                            $i->replaceBindable($trade, $savedTrade);
                            $i->saveBindings($savedTrade);
                        }

                        $trades[$trade->timestamp] = $trade = $savedTrade;
                    }
                });
            }
            else
            {
                //TODO:: handle no signal case
            }
        }

        return \collect($trades);
    }

    protected function getSignalGeneratorResult(\stdClass $candle, ?Indicator &$indicator = null): ?array
    {
        foreach ($this->signalGenerators as $class => $generator)
        {
            $indicator = $this->getSignalIndicator($class);
            $indicatorCandle = $indicator->candle();

            if ($indicatorCandle->t < $candle->t)
            {
                while ($indicator->candle()->t < $candle->t)
                {
                    $generator->next();

                    if ($indicator->candle()->t == $candle->t)
                    {
                        return $generator->current();
                    }
                }
            }
            else if ($indicatorCandle->t == $candle->t)
            {
                return $generator->current();
            }
        }
        return null;
    }

    protected function getSignalIndicator(string $class): Indicator
    {
        return $this->signalIndicators[$class];
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
}