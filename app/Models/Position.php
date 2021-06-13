<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int     id
 * @property boolean is_open
 * @property string  exchange
 * @property string  account
 * @property string  symbol
 * @property string  side
 * @property float   quantity
 * @property float   entry_price
 * @property float   avg_price
 * @property float   liq_price
 * @property float   margin
 * @property float   pnl
 * @property float   stop_price
 * @property float   take_profit_price
 * @property mixed   created_at
 * @property mixed   updated_at
 */
class Position extends Model
{
    use HasFactory;

    protected $table = 'positions';
}
