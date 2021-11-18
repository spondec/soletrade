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

        //this object shouldn't be in a loop anymore
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

    protected function scan(): void
    {
        $this->index = -1;

        $lastCandle = $this->symbolRepo->fetchLastCandle($this->symbol);

        $iterator = $this->data->getIterator();

        while ($iterator->valid())
        {
            $value = $iterator->current();
            $this->index++;
            $openTime = $iterator->key();
            $this->current = $openTime;
            $iterator->next();
            $nextOpenTime = $iterator->key();

            /** @var Signal $signal */
            $signal = ($this->signalCallback)(signal: $this->setupSignal(), indicator: $this, value: $value);

            if ($signal)
            {
                $signal->timestamp = $openTime;
                $signal->price_date = $this->getPriceDate($openTime, $nextOpenTime);
                $signal->is_confirmed = $nextOpenTime || ($lastCandle && $lastCandle->t > $openTime);//the candle is closed

                $this->saveSignal($signal);
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

    public function closePrice(): float
    {
        $candle = $this->candles[$this->index + $this->gap];

        if ($candle->t !== $this->current)
        {
            throw new \LogicException('Timestamp mismatched.');
        }

        return (float)$candle->c;
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

    public function symbolRepo(): SymbolRepository
    {
        return $this->symbolRepo;
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

    protected function getPriceDate(int $openTime, int|null $nextOpenTime): int
    {
        if ($nextOpenTime)
        {
            return $nextOpenTime - 1;
        }

        if ($nextCandle = $this->symbolRepo
            ->fetchNextCandle($this->symbol->id, $openTime))
        {
            return $nextCandle->t - 1;
        }

        return $this->symbol->last_update;
    }
}