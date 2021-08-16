<?php

namespace App\Trade\Indicator;

use App\Models\Signature;
use App\Models\Symbol;
use App\Models\Signal;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Helper\ClosureHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class AbstractIndicator
{
    use HasName;
    use HasSignature;

    protected ?int $prev = null;
    protected ?int $current = null;

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

    /** @var Signature */
    protected Model $signature;

    protected array $config = [];
    protected array $data = [];
    /**
     * @var Signal[]
     */
    private Collection $signals;
    private Signature $signalSignature;

    abstract protected function run(): array;

    public function __construct(protected Symbol     $symbol,
                                protected Collection $candles,
                                array                $config = [],
                                protected ?\Closure  $signalCallback = null)
    {
        if ($config)
        {
            $this->config = array_merge_recursive_distinct($this->config, $config);
        }

        $this->signature = $this->register([
            'contents' => $this->contents()
        ]);

        $this->data = $this->combineTimestamps($this->run());
        $this->signals = new Collection([]);

        if ($signalCallback)
        {
            $this->signalSignature = $this->register([
                'config'             => $this->config,
                'signalCallbackHash' => ClosureHash::from($this->signalCallback)
            ]);
            $this->scan();
        }
    }

    public function setupSignal(): Signal
    {
        $signal = new Signal();

        $signal->symbol_id = $this->symbol->id;
        $signal->indicator_id = $this->id();
        $signal->signature_id = $this->signalSignature->id;

        return $signal;
    }

    protected function checkSignal(int $timestamp, mixed $value): ?Signal
    {
        $callback = $this->signalCallback;
        return $callback(signal: $this->setupSignal(),
            indicator: $this,
            timestamp: $timestamp,
            value: $value);
    }

    protected function scan(): void
    {
        foreach ($this->data as $timestamp => $value)
        {
            $this->current = $timestamp;
            if ($signal = $this->checkSignal($timestamp, $value))
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

                $this->signals[$timestamp] = $signal;
            }

            $this->prev = $timestamp;
        }
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

    protected function combineTimestamps(?array $data): array
    {
        if (!$data)
        {
            return [];
        }

        $timestamps = array_slice($this->timestamps(), ($length = count($data)) * -1, $length);

        return array_combine($timestamps, $data);
    }

    protected function closes(): array
    {
        return array_column($this->candles->all(), 'c');
    }

    protected function timestamps(): array
    {
        return array_column($this->candles->all(), 't');
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

    public function id(): int
    {
        return $this->signature->id;
    }
}