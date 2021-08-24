<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int     id
 * @property int     exchange_id
 * @property boolean is_open
 * @property string  symbol
 * @property string  side
 * @property float   quantity
 * @property float   quantity_type
 * @property float   price
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
    use HasExchange;

    protected $table = 'positions';
}
