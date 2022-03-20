<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    order_id
 * @property int    trade_id
 * @property int    timestamp
 * @property float  quantity
 * @property float  price
 * @property string commission_asset
 * @property float  commission
 */
class Fill extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected array $unique = ['order_id', 'trade_id'];

    protected static function booted()
    {
        parent::booted();

        static::created(function (Fill $fill) {
            Order::newFill($fill);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
