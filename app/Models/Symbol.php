<?php

namespace App\Models;

use App\Trade\Collection\CandleCollection;
use App\Trade\Indicator\Indicator;
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
 * @property mixed  created_at
 * @property mixed  updated_at
 *
 * @method static SymbolFactory factory($count = null, $state = [])
 */
class Symbol extends Model
{
    use HasExchange, HasFactory;

    const INDICATOR_DIR = "\App\Trade\Indicator";

    protected $table = 'symbols';

    protected ?CandleCollection $candles = null;

    /** @var Indicator[] */
    protected ?Collection $indicators = null;

    protected ?int $limit = null;
    protected ?int $end = null;
    protected ?int $start = null;

    public function toArray()
    {
        return \array_merge(parent::toArray(), [
            'start'      => $this->start,
            'end'        => $this->end,
            'limit'      => $this->limit,
            'candles'    => $this->candles?->toArray() ?? [],
            'indicators' => $this->indicators?->map(static fn(Indicator $i) => [
                    'data'        => $i->raw($i->data()),
                    'progressive' => $i->raw($i->progressiveData())
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

        try
        {
            if ($this->candles && $end == $this->end && $start == $this->start && $limit == $this->limit)
            {
                return $this->candles;
            }
        } finally
        {
            $this->start = $start;
            $this->end = $end;
            $this->limit = $limit;
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
        return $this->candles = new CandleCollection($order === 'DESC' ? $candles->reverse()->values() : $candles);
    }

    public function addIndicator(Indicator $indicator): void
    {
        if ($indicator->symbol() !== $this)
        {
            throw new \InvalidArgumentException("Indicator {$indicator::name()} doesn't belong to this symbol instance.");
        }

        if (!$this->indicators)
        {
            $this->indicators = new Collection();
        }

        $this->indicators[$indicator->name()] = $indicator;
    }

    public function updateCandlesIfOlderThan(int $seconds, int $maxRunTime = 0)
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

    public function indicator(string $name): Indicator
    {
        return $this->indicators[$name] ??
            throw new \LogicException(
                $this->indicatorExists($name) ?
                    "{$name} hasn't been set for this instance." :
                    "{$name} doesn't exist as an indicator.");
    }

    protected function indicatorExists(string $name): bool
    {
        return \class_exists(static::INDICATOR_DIR . "\\" . $name);
    }
}
