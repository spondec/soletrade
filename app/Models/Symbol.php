<?php

namespace App\Models;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;
use App\Trade\Repository\SymbolRepository;
use Database\Factories\SymbolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int    id
 * @property int    exchange_id
 * @property string symbol
 * @property string interval
 * @property int    last_update
 * @property int    last_candle_time
 * @property mixed  created_at
 * @property mixed  updated_at
 *
 * @method static SymbolFactory factory($count = null, $state = [])
 */
class Symbol extends Model
{
    use HasExchange, HasFactory;

    protected $table = 'symbols';

    /** @var Indicator[] */
    protected ?Collection $indicators = null;
    public ?CandleCollection $candles = null;

    public function toArray()
    {
        return \array_merge(parent::toArray(), [
            'start'      => $this->start,
            'end'        => $this->end,
            'limit'      => $this->limit,
            'candles'    => $this->exists ? $this->candles?->toArray() ?? [] : [],
            'indicators' => $this->indicators?->map(static fn(Indicator $i) => [
                    'name'   => $i::name(),
                    'data'   => $i->raw($i->data()),
                    'config' => $i->config(),
                ])?->toArray() ?? []
        ]);
    }

    public function candles(?int $limit = null, ?int $start = null, ?int $end = null): CandleCollection
    {
        if (!$this->exists)
        {
            throw new \LogicException('Can not get candles for an unsaved symbol.');
        }

        if ($end && $start && $limit)
        {
            throw new \UnexpectedValueException('Argument $limit can not be passed along with $start and $end.');
        }

        $query = DB::table('candles')
            ->where('symbol_id', $this->id)
            ->orderBy('t', $order = $start ? 'ASC' : 'DESC');

        if ($limit)
        {
            $query->limit($limit);
        }
        if ($end)
        {
            $query->where('t', '<=', $end);
        }
        if ($start)
        {
            $query->where('t', '>=', $start);
        }

        $candles = $query->get();
        return $this->candles = new CandleCollection($order === 'DESC' ? $candles->reverse()->values()->all() : $candles->all());
    }

    public function addIndicator(Indicator $indicator): void
    {
        if ($indicator->symbol() !== $this)
        {
            throw new \InvalidArgumentException("Indicator must be attached to the same symbol.");
        }

        if (!$this->indicators)
        {
            $this->indicators = new Collection();
        }

        $this->indicators[$indicator->alias] = $indicator;
    }

    public function updateCandlesIfOlderThan(int $seconds, int $maxRunTime = 0): void
    {
        if ($seconds > 0 && $this->last_update + $seconds * 1000 <= \time() * 1000)
        {
            $this->updateCandles($maxRunTime);
        }
    }

    public function updateCandles(int $maxRunTime = 0): void
    {
        $this->exchange()->update()->bySymbol($this, $maxRunTime);
    }

    public function indicator(string $alias): Indicator
    {
        return $this->indicators[$alias];
    }

    public function lastCandle(): object
    {
        return \DB::table('candles')
            ->where('symbol_id', $this->id)
            ->orderBy('t', 'DESC')
            ->first();
    }

    public function firstCandle(): object
    {
        return \DB::table('candles')
            ->where('symbol_id', $this->id)
            ->orderBy('t', 'ASC')
            ->first();
    }
}