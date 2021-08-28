<?php

namespace App\Models;

use App\Trade\Indicator\AbstractIndicator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int    id
 * @property string symbol
 * @property string interval
 * @property string exchange
 * @property int    last_update
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class Symbol extends Model
{
    use HasFactory;
    use HasExchange;

    const INDICATOR_DIR = "\App\Trade\Indicator";

    protected $table = 'symbols';

    protected ?Collection $candles = null;

    /** @var AbstractIndicator[] */
    protected array $indicators = [];

    protected ?int $limit = null;
    protected ?int $end = null;
    protected ?int $start = null;

    public function cachedSignals(): array
    {
        $signals = [];
        foreach ($this->indicators as $indicator)
        {
            $signals[$indicator::name()] = $indicator->signals();
        }
//        usort($signals, fn(Signal $a, Signal $b) => $a->timestamp <=> $b->timestamp);
        return $signals;
    }

    public function signals()
    {
        return $this->hasMany(Signal::class);
    }

    public function trades()
    {
        return $this->hasMany(TradeSetup::class);
    }

    public function toArray()
    {
        $result = parent::toArray();

//        $result['signals'] = $this->signals->toArray();
//        $result['trades'] = $this->trades->toArray();
        $result['before'] = $this->end;
        $result['limit'] = $this->limit;
        $result['candles'] = $this->candles?->toArray() ?? [];
        $result['indicators'] = array_map(
                fn(AbstractIndicator $i): array => $i->raw(),
                $this->indicators) ?? [];

        return $result;
    }

    public function candles(?int $limit = null, ?int $start = null, ?int $end = null): ?Collection
    {
        if (!$this->exists)
        {
            return null;
        }

        if ($end && $start && $limit)
        {
            throw new \UnexpectedValueException('Argument $limit can not be passed along with $after and $before.');
        }

        try
        {
            if ($this->candles && $end == $this->end && $start == $this->start && $limit == $this->limit)
            {
                return $this->candles;
            }
        } finally
        {
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
            $query->where('t', '<', $end);
        }
        if ($start)
        {
            $query->where('t', '>', $start);
        }

        $candles = $query->get();
        return $this->candles = ($order === 'DESC' ? $candles->reverse()->values() : $candles);
    }

    public function addIndicator(AbstractIndicator $indicator): void
    {
        if ($indicator->symbol() !== $this)
        {
            throw new \InvalidArgumentException("{$indicator::name()} doesn't belong to this instance.");
        }

        $this->indicators[$indicator->name()] = $indicator;
    }

    public function indicator(string $name): AbstractIndicator
    {
        return $this->indicators[$name] ??
            throw new \LogicException(
                $this->indicatorExists($name) ?
                    "{$name} hasn't been set for this instance." :
                    "{$name} doesn't exist as an indicator.");
    }

    protected function indicatorExists(string $name): bool
    {
        return class_exists(static::INDICATOR_DIR . "\\" . $name);
    }
}
