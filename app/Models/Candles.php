<?php

namespace App\Models;

use App\Trade\Indicator\AbstractIndicator;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int    id
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

    const MAX_LENGTH = 1000;
    const INDICATOR_DIR = "\App\Trade\Indicator";

    protected $table = 'candles';

    protected $guarded = [];
    protected $casts = [
        'data' => 'array',
        'map'  => 'array',
    ];

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
            if ($data = $candles->data)
            {
                $first = reset($data);
                $last = end($data);
                $timestamp = $candles->map('timestamp');

                $candles->start_date = $first[$timestamp];
                $candles->end_date = $last[$timestamp];
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
        $this->attributes['data'] = json_encode($value);
    }

    public function last(): array
    {
        if (empty($data = $this->data))
        {
            return [];
        }

        return end($data);
    }

    public function first(): array
    {
        if (empty($data = $this->data))
        {
            return [];
        }

        return reset($data);
    }

    public function length(): int
    {
        return count($this->data ?? []);
    }

    public function map($key): ?string
    {
        return $this->map[$key] ?? null;
    }

    public function pop()
    {
        $data = $this->data;
        array_pop($data);
        $this->data = $data;
    }
}
