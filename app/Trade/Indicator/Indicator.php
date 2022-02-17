<?php

declare(strict_types=1);

namespace App\Trade\Indicator;

use App\Models\Signal;
use App\Models\Signature;
use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\Binding\Binder;
use App\Trade\Binding\CanBind;
use App\Trade\Collection\CandleCollection;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Helper\ClosureHash;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\Pure;

abstract class Indicator implements Binder
{
    protected array $config = [];

    use HasName;
    use HasSignature;
    use HasConfig;
    use CanBind;

    protected readonly SymbolRepository $symbolRepo;
    protected bool $isProgressive = false;
    protected bool $recalculate = true;
    protected \stdClass $progressingCandle;
    protected readonly Symbol $progressiveSymbol;
    private ?int $prev = null;
    private ?int $current = null;
    private ?int $next = null;
    private readonly Collection $data;
    /** @var Signal[] */
    private Collection $signals;
    private int $gap = 0;
    private int $index = 0;

    /** @var \Iterator[] */
    private Collection $progressiveIterators;
    private Collection $progressiveCandles;
    private Collection $progressiveData;

    public function __construct(protected Symbol         $symbol,
                                private CandleCollection $candles,
                                array                    $config = [])
    {
        $this->mergeConfig($config);
        $this->symbolRepo = App::make(SymbolRepository::class);

        /** @var Signature signature */
        $this->signature = $this->register(['contents' => $this->contents(),
                                            'config'   => $this->config]);

        $this->loadConfigDependencies();

        $this->progressiveData = new Collection();
        $this->progressiveIterators = new Collection();
        $this->progressiveCandles = new Collection();
        $this->signals = new Collection();

        $this->setup();

        if ($count = $candles->count())
        {
            $data = $this->calculate($this->candles);
            $this->gap = $count - \count($data);

            if ($this->gap < 0)
            {
                throw new \LogicException(static::name() . ' data count cannot exceed the candle count.');
            }

            $this->data = new Collection($this->combineTimestamps($data));
        }
        else
        {
            $this->data = new Collection();
        }
    }

    protected function loadConfigDependencies(): void
    {
        if ($this->isProgressive)
        {
            if ($progressiveInterval = $this->config('progressiveInterval'))
            {
                $this->progressiveSymbol = $this->symbolRepo->fetchSymbol(
                    $this->symbol->exchange(),
                    $this->symbol->symbol,
                    $progressiveInterval);
                $this->progressiveSymbol->updateCandlesIfOlderThan(
                    (int)(($this->symbol->last_update - $this->progressiveSymbol->last_update) / 1000));
            }
            else
            {
                //not specified an interval in config
                $this->isProgressive = false;
            }
        }
    }

    protected function setup(): void
    {

    }

    abstract protected function calculate(CandleCollection $candles): array;

    #[Pure] protected function combineTimestamps(?array $data): array
    {
        if (!$data)
        {
            return [];
        }

        $timestamps = \array_slice($this->candles->timestamps(), ($length = \count($data)) * -1, $length);

        return \array_combine($timestamps, $data);
    }

    public function isProgressive(): bool
    {
        return $this->isProgressive;
    }

    public function progressiveData(): Collection
    {
        return $this->progressiveData;
    }

    final public function getBindValue(int|string $bind, ?int $timestamp = null, ?Symbol $progressiveSymbol = null): mixed
    {
        if (!$progressiveSymbol || !$this->recalculate)
        {
            if ($timestamp)
            {
                return $this->getBind($bind, $this->getEqualOrClosestValue($timestamp));
            }

            return $this->getBind($bind, $this->current());
        }

        return $this->getBind($bind, $this->getProgressiveValue($progressiveSymbol, $timestamp));
    }

    protected function getBind(int|string $bind, mixed $value): mixed
    {
        $this->throwBindingIsDisabled();
    }

    private function throwBindingIsDisabled(): never
    {
        throw new \LogicException('Binding is disabled by default. 
        To enable it, override getBindable() and getBind().');
    }

    protected function getEqualOrClosestValue(int $timestamp)
    {
        if ($value = $this->getData($timestamp))
        {
            return $value;
        }

        foreach ($this->data as $t => $value)
        {
            if ($t > $timestamp)
            {
                return $_prev;
            }
            $_prev = $value;
        }

        return $value;
    }

    protected function getData(int $timestamp): mixed
    {
        return $this->progressiveData[$timestamp] ?? $this->data[$timestamp] ?? null;
    }

    public function current(): mixed
    {
        return $this->data[$this->current] ?? throw new \LogicException('Indicator is not in a loop.');
    }

    protected function getProgressiveValue(Symbol $progressiveSymbol, int $timestamp): mixed
    {
        $this->assertSameSymbolDifferentInterval($progressiveSymbol);

        if (($value = $this->getProgressiveData($timestamp)) === null)
        {
            $this->candles->findPrevNextCandle($timestamp, $prev, $next, $prevKey, $nextKey);

            if (!$candles = $this->getProgressiveIterator($key = "$prev->t-$next->t"))
            {
                $candles = $this->progressiveCandles($progressiveSymbol, $prev, $next);
                $this->registerProgressiveIterator($key, $candles);
            }

            while ($candles->valid())
            {
                $candle = $candles->current();
                $value = $this->recalculateProgressively($prevKey, $candle);

                $candles->next();

                if ($candle->t == $timestamp)
                {
                    break;
                }
            }
        }
        return $value ?? throw new \LogicException("Progressive recalculation failed for timestamp: $timestamp");
    }

    protected function assertSameSymbolDifferentInterval(Symbol $symbol): void
    {
        if ($symbol->symbol !== $this->symbol->symbol || $symbol->exchange_id !== $this->symbol->exchange_id)
        {
            throw new \InvalidArgumentException("Argument \$symbol's name and exchange ID does not match with member symbol.");
        }

        if ($symbol->interval === $this->symbol->interval)
        {
            throw new \InvalidArgumentException("Argument \$symbol's interval should not be the same as member symbol's interval.");
        }
    }

    protected function getProgressiveData(int $timestamp): mixed
    {
        return $this->progressiveData[$timestamp] ?? null;
    }

    protected function getProgressiveIterator(string $key): \Iterator
    {
        return $this->progressiveIterators[$key] ?? null;
    }

    protected function registerProgressiveIterator(string $key, \Iterator $iterator): void
    {
        $this->progressiveIterators[$key] = $iterator;
    }

    public function prev(): mixed
    {
        return $this->data[$this->prev] ?? null;
    }

    public function next(): mixed
    {
        return $this->data[$this->next] ?? null;
    }

    public function id(): int
    {
        return $this->signature->id;
    }

    public function closePrice(): float
    {
        return (float)$this->candle()->c;
    }

    /**
     * Use offset to access previous/next candles.
     */
    public function candle(int $offset = 0): \stdClass
    {
        if ($this->isProgressive && !$offset)
        {
            return $this->progressingCandle;
        }

        $candle = $this->candles[$this->index + $this->gap + $offset];

        if (!$this->isProgressive && $candle->t !== $this->current)
        {
            throw new \LogicException("Expected timestamp: $this->current, given: $candle->t");
        }

        return $candle;
    }

    #[Pure] public function raw(Collection $data): array
    {
        return $data->all();
    }

    /**
     * @return Signal[]
     */
    public function signals(): Collection
    {
        return $this->signals;
    }

    public function symbol(): Symbol
    {
        return $this->symbol;
    }

    public function data(): Collection
    {
        return $this->data;
    }

    public function getBindable(): array
    {
        $this->throwBindingIsDisabled();
    }

    public function scan(\Closure $signalCallback): \Generator
    {
        $this->index = -1;

        $this->verifySignalCallback($signalCallback);
        $signalSignature = $this->getSignalCallbackSignature($signalCallback);
        $lastCandle = $this->symbolRepo->fetchLastCandle($this->symbol);

        $signal = $this->setupSignal($signalSignature);
        $newSignal = null;

        $iterator = $this->data->getIterator();
        $unconfirmed = [];
        while ($iterator->valid())
        {
            $value = $iterator->current();
            $this->index++;
            $this->current = $openTime = $key = $iterator->key();
            $iterator->next();
            $nextOpenTime = $iterator->key();

            if ($this->isProgressive)
            {
                $candles = $this->progressiveCandles($this->progressiveSymbol,
                    $this->candles[$currentKey = $this->index + $this->gap],
                    $this->candles[$currentKey + 1] ?? null);
                while ($candles->valid())
                {
                    $candle = $this->progressingCandle = $candles->current();
                    $candles->next();
                    $next = $candles->current();

                    if ($this->recalculate)
                    {
                        $value = $this->recalculateProgressively($currentKey, $candle);
                    }

                    /** @var Signal|null $newSignal */
                    if ($newSignal = $signalCallback(signal: $signal, indicator: $this, value: $value))
                    {
                        $priceDate = $this->symbolRepo->getPriceDate($openTime = $candle->t,
                            $nextOpenTime = $next?->t,
                            $this->progressiveSymbol);
                        break; //only one signal per candle is allowed
                    }

                    $priceDate = null;
                }
            }
            else
            {
                /** @var Signal|null $newSignal */
                $newSignal = $signalCallback(signal: $signal, indicator: $this, value: $value);
            }

            $priceDate = $this->symbolRepo->getPriceDate($openTime, $nextOpenTime, $this->symbol);

            if ($newSignal)
            {
                $newSignal->timestamp = $openTime;
                $newSignal->price_date = $priceDate;
                $newSignal->is_confirmed = $nextOpenTime || ($lastCandle && $lastCandle->t > $openTime);//the candle is closed

                $newSignal = $this->saveSignal($newSignal);
                $signal = $this->setupSignal($signalSignature);
            }
            else
            {
                //a possible signal that is no longer valid
                // but might exist in the database
                //needs to be marked as unconfirmed
                $unconfirmed[] = $openTime;
            }

            $this->prev = $key;

            yield ['signal'     => $newSignal,
                   'timestamp'  => $openTime,
                   'price_date' => $priceDate] ?? null;
        }

        if ($unconfirmed)
        {
            $this->unconfirmSignals($unconfirmed, $signalSignature);
        }

        //the loop is over, reset state
        //loop-dependent methods will error out
        $this->current = $this->prev = $this->next = null;
    }

    protected function verifySignalCallback(\Closure $callback): void
    {
        $type = Signal::class;
        $reflection = new \ReflectionFunction($callback);

        if (!($returnType = $reflection->getReturnType()) ||
            !$returnType instanceof \ReflectionNamedType ||
            !$returnType->allowsNull() ||
            $returnType->getName() !== $type)
        {
            throw new \InvalidArgumentException("Signal callback must have a return type of $type and nullable.");
        }
    }

    protected function getSignalCallbackSignature(\Closure $callback): Signature
    {
        return $this->register(['config'             => $this->config,
                                'signalCallbackHash' => ClosureHash::from($callback)]);
    }

    public function setupSignal(Signature $signalSignature): Signal
    {
        $signal = new Signal();

        $signal->symbol()->associate($this->symbol);
        $signal->indicator()->associate($this->signature);
        $signal->signature()->associate($signalSignature);

        return $signal;
    }

    protected function progressiveCandles(Symbol $progressiveSymbol, \stdClass $current, ?\stdClass $next): \Generator
    {
        $nextCandle = $next ?: $this->symbolRepo->fetchNextCandle($this->symbol->id, $current->t);

        if ($nextCandle)
        {
            $candles = $this->symbolRepo->assertCandlesBetween($progressiveSymbol,
                $current->t,
                $nextCandle->t,
                $progressiveSymbol->interval,
                true);

            if ($candles->last()->t >= $nextCandle->t)
            {
                $candles->pop();
            }
        }
        else
        {
            $candles = $this->symbolRepo->assertCandlesLimit($progressiveSymbol,
                $current->t,
                null,
                $progressiveSymbol->interval,
                true);
        }

        if ($candles->first()->t < $current->t || ($nextCandle && $candles->last()->t >= $nextCandle->t))
        {
            throw new \RangeException('Progressive candles are not properly ranged . ');
        }

        $merged = [
            'o' => null,
            'h' => null,
            'l' => null,
        ];

        foreach ($candles as $candle)
        {
            $merged['t'] = $candle->t;//TODO don't
            $merged['c'] = $candle->c;

            if (!$merged['o'])
            {
                $merged['o'] = $candle->o;
            }

            if (!$merged['h'] || $merged['h'] < $candle->h)
            {
                $merged['h'] = $candle->h;
            }

            if (!$merged['l'] || $merged['l'] > $candle->l)
            {
                $merged['l'] = $candle->l;
            }

            yield $this->progressiveCandles[$merged['t']] = (object)$merged;
        }
    }

    protected function recalculateProgressively(int $startIndex, \stdClass $candle): mixed
    {
        $data = $this->recalculate($this->getCalculableMinPrevCandles($startIndex, $candle));
        return $this->progressiveData[$candle->t] = \end($data);
    }

    protected function recalculate(CandleCollection $candles): array
    {
        if (!$data = $this->calculate($candles))
        {
            throw new \LogicException('Recalculation resulted in zero values. Expected at least one.');
        }

        return $data;
    }

    protected function getCalculableMinPrevCandles(int $startIndex, \stdClass $candle): CandleCollection
    {
        $prevCandles = $this->candles->previousCandles($this->gap, $startIndex);
        $prevCandles[$candle->t] = $candle;
        return $prevCandles;
    }

    /**
     * @param Signal $signal
     */
    protected function saveSignal(Signal $signal): Signal
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $this->signals[] = $signal = $signal->updateUniqueOrCreate();
        $signal->setIndicator($this);
        return $signal;
    }

    protected function unconfirmSignals(array $unconfirmed, Signature $signalSignature): void
    {
        DB::table('signals')
            ->where('symbol_id', $this->symbol->id)
            ->where('indicator_id', $this->signature->id)
            ->where('signature_id', $signalSignature->id)
            ->whereIn('timestamp', $unconfirmed)
            ->update(['is_confirmed' => false]);
    }

    final protected function getDefaultConfig(): array
    {
        return [
            'progressiveInterval' => null
        ];
    }
}