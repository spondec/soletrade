<?php

namespace App\Models;

use App\Trade\Indicator\AbstractIndicator;
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
 */
class Symbol extends Model
{
    use HasExchange;

    const INDICATOR_DIR = "\App\Trade\Indicator";

    protected $table = 'symbols';

    protected ?Collection $candles = null;

    /** @var AbstractIndicator[] */
    protected ?Collection $indicators = null;

    protected ?int $limit = null;
    protected ?int $end = null;
    protected ?int $start = null;

    public function getSignals(): Collection
    {
        $signals = new Collection();
        foreach ($this->indicators as $indicator)
        {
            $signals[$indicator::name()] = $indicator->signals();
        }
//        usort($signals, fn(Signal $a, Signal $b) => $a->timestamp <=> $b->timestamp);
        return $signals;
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'start'      => $this->start,
            'end'        => $this->end,
            'limit'      => $this->limit,
            'candles'    => $this->candles?->toArray() ?? [],
            'indicators' => $this->indicators?->map(fn(AbstractIndicator $i) => $i->raw())?->toArray() ?? []
        ]);
    }

    public function candles(?int $limit = null, ?int $start = null, ?int $end = null): Collection
    {
        if (!$this->exists)
        {
            throw new \LogicException('Can not get candles for unsaved symbol.');
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
        return $this->candles = ($order === 'DESC' ? $candles->reverse()->values() : $candles);
    }

    public function addIndicator(AbstractIndicator $indicator): void
    {
        if (!$this->indicators)
        {
            $this->indicators = new Collection();
        }

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
