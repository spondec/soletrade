<?php

namespace App\Trade\Indicator;

use App\Models\Signature;
use App\Models\Symbol;
use App\Models\Signal;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Helper\ClosureHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class AbstractIndicator
{
    use HasName;
    use HasSignature;
    use HasConfig;

    protected ?int $prev = null;
    protected ?int $current = null;

    /** @var Signature */
    protected Model $signature;

    protected array $config = [];

    protected array $data = [];
    /**
     * @var Signal[]
     */
    private Collection $signals;
    private Signature $signalSignature;

    private int $gap = 0;
    private int $index = 0;

    public function __construct(protected Symbol     $symbol,
                                protected Collection $candles,
                                array                $config = [],
                                protected ?\Closure  $signalCallback = null)
    {
        $this->mergeConfig($config);

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

    abstract protected function run(): array;

    /**
     * Use offset to access previous candles.
     */
    public function candle(int $offset = 0): \stdClass
    {
        return $this->candles[($this->index - $offset) + $this->gap];
    }

    protected function scan(): void
    {
        $this->index = -1;
        foreach ($this->data as $timestamp => $value)
        {
            $this->index++;
            $this->current = $timestamp;
            if ($signal = $this->checkSignal($value))
            {
                $signal->timestamp = $timestamp;
                $signal->hash = $this->hash(json_encode($signal->attributesToArray()));
                $existing = Signal::query()->where('hash', $signal->hash)->first();

                if ($existing)
                {
                    $signal = $existing;
                }
                else
                {
                    $signal->save();
                }

                $this->signals[] = $signal;
            }

            $this->prev = $timestamp;
        }
    }

    protected function checkSignal(mixed $value): ?Signal
    {
        $callback = $this->signalCallback;
        return $callback(signal: $this->setupSignal(),
            indicator: $this,
            value: $value);
    }

    public function setupSignal(): Signal
    {
        $signal = new Signal();

        $signal->symbol_id = $this->symbol->id;
        $signal->indicator_id = $this->id();
        $signal->signature_id = $this->signalSignature->id;

        return $signal;
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
            if ($candle->t == $this->current)
                return $candle->c;
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

    protected function closes(): array
    {
        return array_column($this->candles->all(), 'c');
    }
}