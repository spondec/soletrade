<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int    order_id
 * @property int    timestamp
 * @property float  size
 * @property float  price
 * @property string commission_asset
 * @property float  commission
 * @property int    trade_id
 */
class Fill extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted()
    {
        parent::booted();

        static::created(function (Fill $fill) {
            Order::newFill($fill);
        });
    }
}
