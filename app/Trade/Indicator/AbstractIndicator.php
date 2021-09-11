<?php

declare(strict_types=1);

namespace App\Trade\Indicator;

use App\Models\Signature;
use App\Models\Symbol;
use App\Models\Signal;
use App\Trade\Binding\CanBind;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Helper\ClosureHash;
use Illuminate\Support\Collection;

abstract class AbstractIndicator
{
    use HasName;
    use HasSignature;
    use HasConfig;
    use CanBind;

    protected ?int $prev = null;
    protected ?int $current = null;

    /** @var Signature */
    protected Signature $signature;
    protected array $config = ['binding' => null];
    protected array $data = [];
    private Signature $signalSignature;
    /**
     * @var Signal[]
     */
    private Collection $signals;

    private int $gap = 0;
    private int $index = 0;

    public function __construct(protected Symbol     $symbol,
                                protected Collection $candles,
                                array                $config = [],
                                protected ?\Closure  $signalCallback = null)
    {
        $this->mergeConfig($config);

        /** @var Signature signature */
        $this->signature = $this->register([
            'contents' => $this->contents()
        ]);

        $this->signals = new Collection([]);

        if ($count = $candles->count())
        {
            $data = $this->run();

            $this->gap = $count - count($data);
            if ($this->gap < 0)
            {
                throw new \LogicException(static::name() . ' data count cannot exceed the candle count.');
            }

            $this->data = $this->combineTimestamps($data);

            if ($signalCallback)
            {
                $this->signalSignature = $this->register([
                    'config'             => $this->config,
                    'signalCallbackHash' => ClosureHash::from($this->signalCallback)
                ]);
                $this->scan();
            }
        }
    }

    abstract protected function run(): array;

    protected function combineTimestamps(?array $data): array
    {
        if (!$data)
        {
            return [];
        }

        $timestamps = array_slice($this->timestamps(), ($length = count($data)) * -1, $length);

        return array_combine($timestamps, $data);
    }

    protected function timestamps(): array
    {
        return array_column($this->candles->all(), 't');
    }

    protected function scan(): void
    {
        $this->index = -1;
        /** @var Signal $lastSignal */
        $lastSignal = null;
        foreach ($this->data as $timestamp => $value)
        {
            $this->index++;
            $this->current = $timestamp;

            if ($lastSignal) $this->syncBindings($lastSignal);

            /** @var Signal $signal */
            if ($signal = ($this->signalCallback)(signal: $this->setupSignal(), indicator: $this, value: $value))
            {
                if (next($this->data))
                {
                    $signal->timestamp = $timestamp;
                }
                else
                {
                    $signal->timestamp = time();
                }

                $old = $signal;
                $this->replaceBindable($old, $signal = $signal->updateUniqueOrCreate());
                $this->saveSignal($signal);

                if ($lastSignal) $this->saveSignal($lastSignal);

                $this->signals[] = $lastSignal = $signal;
            }

            $this->prev = $timestamp;
        }

        //Make sure to save the last signal in case of a nonexistent following signal
        if ($lastSignal) $this->saveSignal($lastSignal);
    }

    public function setupSignal(): Signal
    {
        $signal = new Signal();

        $signal->symbol()->associate($this->symbol);
        $signal->indicator()->associate($this->signature);
        $signal->signature()->associate($this->signalSignature);

        return $signal;
    }

    /**
     * @param Signal $signal
     */
    protected function saveSignal(Signal $signal): void
    {
        $signal->save();
        $this->saveBindings($signal, $this->current);
    }

    /**
     * Use offset to access previous candles.
     */
    public function candle(int $offset = 0): \stdClass
    {
        return $this->candles[($this->index - $offset) + $this->gap];
    }

    public function id(): int
    {
        return $this->signature->id;
    }

    public function crossOver(\Closure $x, \Closure $y): bool
    {
        if ($this->prev === null || $this->current === null)
        {
            return false;
        }

        $prevX = $x($this->data[$this->prev]);
        $prevY = $y($this->data[$this->prev]);

        $currentX = $x($this->data[$this->current]);
        $currentY = $y($this->data[$this->current]);

        return $prevX < $prevY && $currentX > $currentY;
    }

    public function crossUnder(\Closure $x, \Closure $y): bool
    {
        if ($this->prev === null || $this->current === null)
        {
            return false;
        }

        $prevX = $x($this->data[$this->prev]);
        $prevY = $y($this->data[$this->prev]);

        $currentX = $x($this->data[$this->current]);
        $currentY = $y($this->data[$this->current]);

        return $prevX > $prevY && $currentX < $currentY;
    }

    public function prev(): mixed
    {
        return $this->data[$this->prev] ?? null;
    }

    public function closePrice(): float
    {
        foreach ($this->candles as $candle)
        {
            if ($candle->t === $this->current)
                return (float)$candle->c;
        }

        throw new \LogicException('Current timestamp does not exist.');
    }

    public function raw(): array
    {
        return $this->data;
    }

    public function candles(): Collection
    {
        return $this->candles;
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

    public function data(): array
    {
        return $this->data;
    }

    protected function getBindable(): array
    {
        return [];
    }

    protected function getBindValue(int|float|string $bind): mixed
    {
        throw new \Exception('getBindValue() must be overridden.');
    }

    protected function closes(): array
    {
        return array_column($this->candles->all(), 'c');
    }
}