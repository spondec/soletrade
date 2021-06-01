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
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class Candles extends Model
{
    use HasFactory;

    const MAX_DATA_LENGTH = 1000;

    protected $table = 'candles';

    public function __get($key)
    {
        $column = $this->map[$key] ?? null;

        if($column !== null)
        {
            return array_column($this->data, $column);
        }

        return parent::__get($key);
    }
}
