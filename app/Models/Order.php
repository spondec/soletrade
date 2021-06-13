<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    id
 * @property bool   is_open
 * @property string exchange
 * @property string account
 * @property string symbol
 * @property string side
 * @property string type
 * @property float  quantity
 * @property float  filled
 * @property float  price
 * @property float  stop_price
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class Order extends Model
{
    protected $table = 'orders';

    use HasFactory;
}
