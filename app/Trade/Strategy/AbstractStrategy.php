<?php

namespace App\Trade\Strategy;

use App\Models\Signal;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Helper\ClosureHash;
use App\Trade\Indicator\AbstractIndicator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

abstract class AbstractStrategy
{
    use HasName;
    use HasSignature;

    protected array $config = ['maxCandles' => 1000];
    protected array $signals = [];
    protected array $indicatorSetup;
    protected array $tradeSetup;

    protected SymbolRepository $symbolRepo;

    abstract protected function indicatorSetup(): array;

    abstract protected function tradeSetup(): array;

    public function __construct(array $config = [])
    {
        if ($config)
        {
            $this->config = array_merge_recursive_distinct($this->config, $config);
        }

        $this->symbolRepo = App::make(SymbolRepository::class);
        $this->indicatorSetup = $this->indicatorSetup();
        $this->tradeSetup = $this->tradeSetup();
        $this->signature = $this->register([
            'contents' => $this->contents()
        ]);
    }

    /**
     * @return TradeSetup[]
     */
    public function addSymbol(Symbol $symbol): array
    {
        foreach ($this->indicatorSetup as $class => $setup)
        {
            $symbol->addIndicator(indicator: new $class(
                symbol: $symbol,
                candles: $symbol->candles(limit: $this->config['maxCandles']),
                config: is_array($setup) ? $setup['config'] ?? [] : [],
                signalCallback: $setup instanceof \Closure ? $setup : $setup['signal'] ?? null
            ));
        }

        return $this->evaluateSignals($symbol);
    }

    /**
     * @param Signal[] $signals
     */
    protected function setupTrade(array $config, array $signals): TradeSetup
    {
        $signature = $this->register([
            'config'   => $this->config,
            'signals'  => $config['signals'],
            'callback' => isset($config['callback']) ? ClosureHash::from($config['callback']) : null
        ]);

        $tradeSetup = new TradeSetup();
        $tradeSetup->signals = $signals;
        $lastSignal = end($signals);

        $tradeSetup->signal_count = count($signals);

        $tradeSetup->name = implode('|', array_map(fn(Signal $v) => $v->name, $signals));
        $tradeSetup->entry_price = $lastSignal->price;
        $tradeSetup->side = $lastSignal->side;
        $tradeSetup->timestamp = $lastSignal->timestamp;
        $tradeSetup->signature_id = $signature->id;

        return $tradeSetup;
    }

    protected function saveTrade(TradeSetup $tradeSetup)
    {
        $tradeSetup->hash = $this->hash(json_encode($tradeSetup->attributesToArray()));

        /** @var TradeSetup $existing */
        $existing = TradeSetup::query()->where('hash', $tradeSetup->hash)->first();

        if ($existing)
        {
            $tradeSetup = $existing;
        }
        else
        {
            DB::transaction(function () use ($tradeSetup) {
                $tradeSetup->save();
                $tradeSetup->signals()->saveMany($tradeSetup->signals);
            });
        }

        return $tradeSetup;
    }

    /**
     * @return TradeSetup[]
     */
    protected function evaluateSignals(Symbol $symbol): array
    {
        $signals = $this->getSignals($symbol);

        if (!$signals)
        {
            return [];
        }

        $tradeSetups = [];

        foreach ($this->tradeSetup as $key => $config)
        {
            $indicators = $config['signals'];
            $_signals = $signals;
            $requiredTotal = count($config['signals']);
            $requiredSignals = [];

            while (count($_signals[$indicators[0]]))
            {
                foreach ($indicators as $indicator)
                {
                    /* @var Signal $signal */
                    foreach ($_signals[$indicator] as $timestamp => $signal)
                    {
                        unset($_signals[$indicator][$timestamp]);

                        /** @var Signal $lastSignal */
                        $lastSignal = end($requiredSignals);

                        if (!$lastSignal || (
                                $timestamp >= $lastSignal->timestamp &&
                                $lastSignal->side == $signal->side
                            )
                        )
                        {
                            $requiredSignals[] = $signal;
                            break;
                        }
                    }
                }

                if ($requiredTotal == count($requiredSignals))
                {
                    $tradeSetup = $this->setupTrade($config, $requiredSignals);
                    $callback = $config['callback'] ?? null;

                    if ($callback instanceof \Closure)
                    {
                        $tradeSetup = $callback($tradeSetup);
                    }

                    $tradeSetup->symbol_id = $symbol->id;
                    $tradeSetups[$key] = $this->saveTrade($tradeSetup);
                }

                $requiredSignals = [];
            }
        }

        return $tradeSetups;
    }

    protected function getSignals(Symbol $symbol): array
    {
        foreach ($this->tradeSetup as $setup)
        {
            /* @var AbstractIndicator $indicator */
            foreach ($setup['signals'] as $indicator)
            {
                $indicator = $symbol->indicator($indicator::name());
                $signals[$indicator::class] = $indicator->signals()->all();
            }
        }

        return $signals;
    }
}