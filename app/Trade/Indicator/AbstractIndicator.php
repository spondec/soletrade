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
use JetBrains\PhpStorm\Pure;

abstract class AbstractIndicator
{
    protected array $config = [];

    use HasName;
    use HasSignature;
    use HasConfig;
    use CanBind;

    private ?int $prev = null;
    private ?int $current = null;
    private ?int $next = null;
    private Collection $data;
    private Signature $signalSignature;
    /**
     * @var Signal[]
     */
    private Collection $signals;
    private int $gap = 0;
    private int $index = 0;

    protected SymbolRepository $symbolRepo;

    protected bool $isProgressive = false;
    protected \stdClass $progressingCandle;
    protected Symbol $progressiveSymbol;

    protected final function getDefaultConfig(): array
    {
        return [
            'progressiveInterval' => null
        ];
    }

    public function __construct(protected Symbol     $symbol,
                                protected Collection $candles,
                                array                $config = [],
                                protected ?\Closure  $signalCallback = null)
    {
        $this->mergeConfig($config);
        $this->symbolRepo = App::make(SymbolRepository::class);

        /** @var Signature signature */
        $this->signature = $this->register([
            'contents' => $this->contents(),
            'config'   => $this->config
        ]);

        $this->loadConfigDependencies();

        $this->signals = new Collection([]);
        $this->setup();

        if ($count = $candles->count())
        {
            $data = $this->run();
            $this->gap = $count - count($data);

            if ($this->gap < 0)
            {
                throw new \LogicException(static::name() . ' data count cannot exceed the candle count.');
            }

            $this->data = new Collection($this->combineTimestamps($data));

            if ($signalCallback)
            {
                $this->signalSignature = $this->register([
                    'config'             => $this->config,
                    'signalCallbackHash' => ClosureHash::from($this->signalCallback)
                ]);
                $this->scan();
            }
        }
        else
        {
            $this->data = new Collection();
        }

        //the loop is over, reset state
        //loop-dependent methods will error out
        $this->current = $this->prev = $this->next = null;
    }

    protected function setup(): void
    {

    }

    abstract protected function run(): array;

    #[Pure] protected function combineTimestamps(?array $data): array
    {
        if (!$data)
        {
            return [];
        }

        $timestamps = array_slice($this->timestamps(), ($length = count($data)) * -1, $length);

        return array_combine($timestamps, $data);
    }

    #[Pure] protected function timestamps(): array
    {
        return array_column($this->candles->all(), 't');
    }

    protected function progressingCandles(\stdClass $current, ?\stdClass $next): \Generator
    {
        $nextCandle = $next ?: $this->symbolRepo->fetchNextCandle($this->symbol->id, $current->t);

        if ($nextCandle)
        {
            $candles = $this->symbolRepo->assertCandlesBetween($this->progressiveSymbol,
                $current->t,
                $nextCandle->t,
                $this->progressiveSymbol->interval,
                true);

            if ($candles->last()->t >= $nextCandle->t)
            {
                $candles->pop();
            }
        }
        else
        {
            $candles = $this->symbolRepo->assertCandlesLimit($this->progressiveSymbol,
                $current->t,
                null,
                $this->progressiveSymbol->interval,
                true);
        }

        if ($candles->first()->t < $current->t || ($nextCandle && $candles->last()->t >= $nextCandle->t))
        {
            throw new \RangeException('Progressive candles are not properly ranged.');
        }

        $merged = [
            'o' => null,
            'h' => null,
            'l' => null,
        ];

        foreach ($candles as $candle)
        {
            $merged['t'] = $candle->t;
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

            yield (object)$merged;
        }
    }

    protected function scan(): void
    {
        $this->index = -1;

        $lastCandle = $this->symbolRepo->fetchLastCandle($this->symbol);

        $signal = $this->setupSignal();

        $iterator = $this->data->getIterator();
        $unconfirmed = [];
        while ($iterator->valid())
        {
            $value = $iterator->current();
            $this->index++;
            $openTime = $iterator->key();
            $this->current = $openTime;
            $iterator->next();
            $nextOpenTime = $iterator->key();

            if ($this->isProgressive)
            {
                $candles = $this->progressingCandles(
                    $this->candles[$this->index + $this->gap],
                    $this->candles[$this->index + $this->gap + 1] ?? null);

                while ($candles->valid())
                {
                    $this->progressingCandle = $candles->current();
                    $candles->next();
                    $next = $candles->current();

                    /** @var Signal $newSignal */
                    $newSignal = ($this->signalCallback)(signal: $signal, indicator: $this, value: $value);

                    if ($newSignal)
                    {
                        $priceDate = $this->getPriceDate($this->progressingCandle->t,
                            $next?->t,
                            $this->progressiveSymbol);
                        break;
                    }
                    $priceDate = null;
                }
            }
            else
            {
                /** @var Signal $newSignal */
                $newSignal = ($this->signalCallback)(signal: $signal, indicator: $this, value: $value);
            }

            if ($newSignal)
            {
                $newSignal->timestamp = $openTime;
                $newSignal->price_date = $priceDate ?? $this->getPriceDate($openTime,
                        $nextOpenTime,
                        $this->symbol);
                $newSignal->is_confirmed = $nextOpenTime || ($lastCandle && $lastCandle->t > $openTime);//the candle is closed

                $this->saveSignal($newSignal);
                $signal = $this->setupSignal();
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
        {
            DB::table('signals')
                ->where('symbol_id', $this->symbol->id)
                ->where('indicator_id', $this->signature->id)
                ->where('signature_id', $this->signalSignature->id)
                ->whereIn('timestamp', $unconfirmed)
                ->update(['is_confirmed' => false]);
        }
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
        $old = $signal;
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $this->replaceBindable($old, $signal = $signal->updateUniqueOrCreate());

        $signal->save();
        $this->saveBindings($signal);

        $this->signals[] = $signal;
    }

    public function prev(): mixed
    {
        return $this->data[$this->prev];
    }

    public function current(): mixed
    {
        return $this->data[$this->current];
    }

    public function next(): mixed
    {
        return $this->data[$this->next];
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

    public function closePrice(): float
    {
        return (float)$this->candle()->c;
    }

    #[Pure] public function raw(): array
    {
        return $this->data->all();
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

    public function data(): Collection
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
            'symbol_id'    => $this->symbol->id,
            'indicator_id' => $this->signature->id
        ];
    }

    protected function getSavePoints(string|int $bind, Signature $signature): array
    {
        return [];
    }

    protected function getBindValue(int|string $bind, ?int $timestamp = null): mixed
    {
        throw new \LogicException('Binding is disabled by default. 
        To enable it, override getBindable(), getBindValue() and getSavePoints().');
    }

    #[Pure] protected function closes(): array
    {
        return array_column($this->candles->all(), 'c');
    }

    #[Pure] protected function highs(): array
    {
        return array_column($this->candles->all(), 'h');
    }

    #[Pure] protected function lows(): array
    {
        return array_column($this->candles->all(), 'l');
    }

    protected function getPriceDate(int $openTime, int|null $nextOpenTime, Symbol $symbol): int
    {
        if ($nextOpenTime)
        {
            return $nextOpenTime - 1000;
        }

        if ($nextCandle = $this->symbolRepo->assertNextCandle($symbol->id, $openTime))
        {
            return $nextCandle->t - 1000;
        }

        return $symbol->last_update;
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
                $this->progressiveSymbol->updateCandles();
            }
            else
            {
                //not specified an interval in config
                $this->isProgressive = false;
            }
        }
    }
}