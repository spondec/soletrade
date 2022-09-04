<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Fill
 *
 * @property int $id
 * @property int $order_id
 * @property int $trade_id
 * @property int $timestamp
 * @property string $quantity
 * @property string $price
 * @property string $commission
 * @property string $commission_asset
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order $order
 * @method static \Database\Factories\FillFactory factory(...$parameters)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill query()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereCommission($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereCommissionAsset($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereCreatedAt($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereOrderId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill wherePrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereQuantity($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereTimestamp($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereTradeId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Fill whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Fill extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected array $unique = ['order_id', 'trade_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

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
