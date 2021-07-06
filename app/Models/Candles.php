<?php

namespace App\Models;

use App\Trade\Indicator\AbstractIndicator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string symbol
 * @property string interval
 * @property string exchange
 * @property array  data
 * @property array  map
 * @property int    length
 * @property int    start_date
 * @property int    end_date
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class Candles extends Model
{
    use HasFactory;

    protected $table = 'candles';
    protected $casts = [
        'data' => 'array',
        'map' => 'array'
    ];

    const MAX_LENGTH = 1000;
    const INDICATOR_DIR = "\App\Trade\Indicator";

    protected $lastKey;
    protected $firstKey;

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

    public function addIndicator(AbstractIndicator $indicator): void
    {
        if ($indicator->getCandles() !== $this)
        {
            throw new \InvalidArgumentException("{$indicator->name()} doesn't belong to this instance.");
        }

        $this->indicators[$indicator->name()] = $indicator;
    }

    public function indicator(string $name): AbstractIndicator
    {
        return $this->indicators[$name] ??
            throw new \LogicException(
                class_exists(self::INDICATOR_DIR . "\\" . $name) ?
                    "{$name} hasn't been set for this instance." :
                    "{$name} doesn't exist as an indicator.");
    }

    protected static function booted()
    {
        parent::booted();

        static::saving(function (self $candles) {
            if ($candles->data)
            {
                $candles->start_date = $candles->first()[$candles->map('open')];
                $candles->end_date = $candles->last()[$candles->map('close')];
            }

            $candles->length = $candles->length();
        });
    }

    public function getLengthAttribute(): int
    {
        return $this->length();
    }

    public function setDataAttribute(array $value): void
    {
        $this->attributes['data'] = $value;
        $this->updateKeys();
    }

    public function last(): array
    {
        return $this->attributes['data'][$this->lastKey];
    }

    public function first(): array
    {
        return $this->attributes['data'][$this->firstKey];
    }

    public function length(): int
    {
        return count($this->attributes['data']);
    }

    public function map($key): mixed
    {
        return $this->attributes['map'][$key];
    }

    protected function updateKeys(): void
    {
        $this->firstKey = array_key_first($this->attributes['data']);
        $this->lastKey = array_key_last($this->attributes['data']);
    }
}
