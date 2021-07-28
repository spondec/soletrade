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
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class Symbol extends Model
{
    use HasFactory;
    use HasExchange;

    const INDICATOR_DIR = "\App\Trade\Indicator";

    protected int $limit = 0;

    protected $table = 'symbols';

    protected ?Collection $candles = null;

    /** @var AbstractIndicator[] */
    protected $indicators = [];

    public function getSignals()
    {
        $signals = [];

        foreach ($this->indicators as $indicator)
        {
            $signals[$indicator->name()] = $indicator->signal();
        }

        return $signals;
    }

    public function setLimit(int $limit)
    {
        if ($limit < $this->limit && $this->candles)
        {
            $this->candles = $this->candles->slice(0, $limit);
        }

        $this->limit = $limit;
    }

    public function candles(): ?Collection
    {
        if (!$this->exists)
        {
            return null;
        }

        if ($this->candles && $this->limit && count($this->candles) == $this->limit)
        {
            return $this->candles;
        }

        $query = DB::table('candles')
            ->where('symbol_id', $this->id)
            ->orderBy('t', 'ASC');

        if ($this->limit)
            $query->limit($this->limit);

        return $this->candles = $query->get();
    }

    public function toArray()
    {
        $attributes = parent::toArray();

        $attributes['data'] = $this->candles()?->toArray() ?? [];

        return $attributes;
    }

    public function addIndicator(AbstractIndicator $indicator): void
    {
        if ($indicator->getSymbol() !== $this)
        {
            throw new \InvalidArgumentException("{$indicator->name()} doesn't belong to this instance.");
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
