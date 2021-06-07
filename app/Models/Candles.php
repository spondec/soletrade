<?php

namespace App\Models;

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

    const MAX_CANDLE_LENGTH = 1000;

    protected $casts = [
        'data' => 'array',
        'map' => 'array'
    ];

    protected $firstKey;
    protected $lastKey;

    protected static function booted()
    {
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

    public function setDataAttribute(array $value)
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

    public function map($key)
    {
        return $this->attributes['map'][$key];
    }

    protected function updateKeys()
    {
        $this->firstKey = array_key_first($this->attributes['data']);
        $this->lastKey = array_key_last($this->attributes['data']);
    }
}
