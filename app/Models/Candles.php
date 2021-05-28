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

    protected $table = 'candles';
}
