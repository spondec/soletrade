<?php

declare(strict_types=1);

namespace App\Trade\Indicator;

use App\Models\Signal;
use App\Models\Signature;
use App\Models\Symbol;
use App\Trade\Binding\CanBind;
use App\Trade\Collection\CandleCollection;
use App\Trade\Contract\Binding\Binder;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Helper\ClosureHash;
use App\Trade\Repository\SymbolRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use JetBrains\PhpStorm\Pure;

abstract class Indicator implements Binder, \ArrayAccess
{
    protected array $config = [];

    use HasName;
    use HasSignature;
    use HasConfig;
    use CanBind;

    protected readonly SymbolRepository $repo;
    private readonly Collection $data;
    /** @var Signal[] */
    private Collection $signals;

    public string $alias;

    protected IndicatorDataSeries $series;
    protected IndicatorSeriesState $state;

    public function __construct(protected Symbol         $symbol,
                                private CandleCollection $candles,
                                array                    $config = [])
    {
        $this->mergeConfig($config);

        $this->alias = $config['alias'] ?? static::name();
        $this->repo = App::make(SymbolRepository::class);

        /** @var Signature signature */
        $this->signature = $this->register([
            'config'   => $this->config,
            'contents' => $this->contents()
        ]);

        $this->loadConfigDependencies();

        $this->signals = new Collection();
        $this->state = new IndicatorSeriesState();

        $this->setup();

        if ($count = $candles->count())
        {
            $data = $this->calculate($this->candles);
            $this->state->gap = $count - \count($data);

            if ($this->state->gap < 0)
            {
                throw new \LogicException(static::name() . ' data count cannot exceed the candle count.');
            }

            $this->data = new Collection($this->combineTimestamps($data));
        }
        else
        {
            $this->data = new Collection();
        }

        $this->series = new IndicatorDataSeries($this->state, $this->data, $this->candles);
    }

    protected function loadConfigDependencies(): void
    {
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

    final public function getBindValue(int|string $bind, ?int $timestamp = null): mixed
    {
        if ($timestamp)
        {
            return $this->getBind($bind, $this->getEqualOrClosestValue($timestamp));
        }

        return $this->getBind($bind, $this->value());
    }

    public function getExtraBindCallbackParams(int|string $bind, ?int $timestamp = null): array
    {
        return ['candle' => $this->candle(timestamp: $timestamp)];
    }

    public function getBindable(): array
    {
        $value = $this->data()->first();
        if (\is_array($value))
        {
            return \array_keys($value);
        }
        return [static::name()];
    }

    protected function getBind(int|string $bind, mixed $value): mixed
    {
        if (\is_array($value))
        {
            return $value[$bind];
        }

        return $value;
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
        return $this->data[$timestamp] ?? null;
    }

    public function value(int $offset = 0): mixed
    {
        return $this->series->get($offset);
    }

    public function price(): float
    {
        return (float)$this->candle()->c;
    }

    public function hasData(): bool
    {
        return $this->data->isNotEmpty();
    }

    /**
     * @param int      $offset Historical value offset.
     *                         1 = previous candle,
     *                         2 = second previous candle, and so on.
     * @param int|null $timestamp
     *
     * @return object
     */
    public function candle(int $offset = 0, int $timestamp = null): object
    {
        if ($timestamp)
        {
            foreach ($this->candles as $candle)
            {
                if ($candle->t == $timestamp)
                {
                    return $candle;
                }
            }
            throw new \LogicException("Candle for timestamp $timestamp not found.");
        }

        return $this->series->candle($offset);
    }

    public function raw(Collection $data): array
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

    public function scan(?\Closure $signalCallback = null): \Generator
    {
        $this->state->index = -1;
        $newSignal = null;

        if ($signalCallback)
        {
            $this->verifySignalCallback($signalCallback);
            $signalSignature = $this->getSignalCallbackSignature($signalCallback);
            $signal = $this->setupSignal($signalSignature);
        }

        $iterator = $this->data->getIterator();
        while ($iterator->valid())
        {
            $value = $iterator->current();
            $this->state->index++;
            $this->state->current = $openTime = $key = $iterator->key();
            $iterator->next();
            $nextOpenTime = $iterator->key();

            if ($signalCallback)
            {
                /** @var Signal|null $newSignal */
                $newSignal = $signalCallback(signal: $signal, indicator: $this, series: $this->series);

                $priceDate = $this->repo->getPriceDate($openTime, $nextOpenTime, $this->symbol);

                if ($newSignal)
                {
                    $newSignal->price ??= $this->candle()->c;
                    $newSignal->timestamp = $openTime;
                    $newSignal->price_date = $priceDate;

                    $newSignal = $this->saveSignal($newSignal);
                    $signal = $this->setupSignal($signalSignature);
                }
            }

            yield ['signal'     => $newSignal,
                   'timestamp'  => $openTime,
                   'price_date' => $priceDate ?? null] ?? null;
        }
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
            throw new \InvalidArgumentException("Signal callback must have a return type of $type and be nullable.");
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

    final protected function getDefaultConfig(): array
    {
        return [];
    }

    /**
     * @param class-string<Indicator> $class
     * @param array                   $config
     *
     * @return Indicator
     */
    protected function indicator(string $class, array $config = []): Indicator
    {
        return new $class(symbol: $this->symbol, candles: $this->candles, config: $config);
    }

    public function offsetExists($offset): bool
    {
        return $this->series->offsetExists($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->series->offsetGet($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->series->offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->series->offsetUnset($offset);
    }
}