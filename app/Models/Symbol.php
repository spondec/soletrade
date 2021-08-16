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
    protected ?int $before = null;
    protected ?int $after = null;

    public function signals()
    {
        $signals = [];

        foreach ($this->indicators as $indicator)
        {
            $signals[$indicator::name()] = $indicator->signals();
        }

        return $signals;
    }

    public function toArray()
    {
        $result = parent::toArray();

        $result['before'] = $this->before;
        $result['limit'] = $this->limit;
        $result['candles'] = $this->candles?->toArray() ?? [];
        $result['indicators'] = array_map(fn(AbstractIndicator $i) => $i->raw(), $this->indicators) ?? [];

        return $result;
    }

    public function candles(?int $limit = null, ?int $before = null, ?int $after = null): ?Collection
    {
        if (!$this->exists)
        {
            return null;
        }

        if ($before && $after && $limit)
        {
            throw new \UnexpectedValueException('Argument $limit can not be passed along with $after and $before.');
        }

        try
        {
            if ($this->candles && $before == $this->before && $after == $this->after && $limit == $this->limit)
            {
                return $this->candles;
            }
        } finally
        {
            $this->before = $before;
            $this->limit = $limit;
        }

        $query = DB::table('candles')
            ->where('symbol_id', $this->id)
            ->orderBy('t', $order = $after ? 'ASC' : 'DESC');

        if ($limit)
        {
            $query->limit($limit);
        }
        if ($before)
        {
            $query->where('t', '<', $before);
        }
        if ($after)
        {
            $query->where('t', '>', $after);
        }

        return $this->candles = $order === 'DESC' ? $query->get()->reverse()->values() : $query->get();
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
