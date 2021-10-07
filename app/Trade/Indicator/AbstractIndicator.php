<?php

declare(strict_types=1);

namespace App\Trade\Indicator;

use App\Models\Signature;
use App\Models\Symbol;
use App\Models\Signal;
use App\Repositories\SymbolRepository;
use App\Trade\Binding\CanBind;
use App\Trade\HasConfig;
use App\Trade\HasName;
use App\Trade\HasSignature;
use App\Trade\Helper\ClosureHash;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

abstract class AbstractIndicator
{
    use HasName;
    use HasSignature;
    use HasConfig;
    use CanBind;

    protected array $config = [];

    protected ?int $prev = null;
    protected ?int $current = null;
    protected ?int $next = null;

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

        /** @var SymbolRepository $repo */
        $repo = App::make(SymbolRepository::class);
        $lastCandle = $repo->fetchLastCandle($this->symbol);

        while ($value = current($this->data))
        {
            $this->index++;
            $this->current = $openTime = key($this->data);
            next($this->data);
            $nextOpenTime = key($this->data);

            /** @var Signal $signal */
            if ($signal = ($this->signalCallback)(signal: $this->setupSignal(), indicator: $this, value: $value))
            {
                $signal->timestamp = $openTime;
                $signal->confirmed = $nextOpenTime || ($lastCandle && $lastCandle->t > $openTime);//the candle is closed

                $old = $signal;
                $this->replaceBindable($old, $signal = $signal->updateUniqueOrCreate());
                $this->saveSignal($signal);
                $this->signals[] = $signal;
            }
            else
            {
                //a possible signal that is no longer valid
                // but might exist in the database
                //needs to be marked as unconfirmed
                $unconfirmed[] = $openTime;
            }

            $this->prev = $openTime;
        }

        if (isset($unconfirmed))
            DB::table('signals')
                ->where('symbol_id', $this->symbol->id)
                ->where('indicator_id', $this->signature->id)
                ->where('signature_id', $this->signalSignature->id)
                ->whereIn('timestamp', $unconfirmed)
                ->update(['confirmed' => false]);
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
        $this->saveBindings($signal);
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

    protected function getBindingSignatureExtra(string|int $bind): array
    {
        return [
            'symbol_id'           => $this->symbol->id,
            'indicator_id'        => $this->signature->id,
            'signal_signature_id' => $this->signalSignature->id
        ];
    }

    protected function getSavePoints(string|int $bind, Signature $signature): array
    {
        return [];
    }

    protected function getBindValue(int|string $bind, ?int $timestamp = null): mixed
    {
        throw new \Exception('Binding is disabled by default. 
        To enable it, override getBindable(), getBindValue() and getSavePoints().');
    }

    protected function closes(): array
    {
        return array_column($this->candles->all(), 'c');
    }
}