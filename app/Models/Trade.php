<?php

namespace App\Models;

use App\Trade\Enum\Side;
use App\Trade\Evaluation\LiveTradeLoop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Trade
 *
 * @property int $id
 * @property Side $side
 * @property int $entry_id
 * @property int|null $exit_id
 * @property int $is_stopped
 * @property int $is_closed
 * @property int $entry_time
 * @property int|null $exit_time
 * @property array $transactions
 * @property string $max_used_size
 * @property string $entry_price
 * @property string|null $exit_price
 * @property string $roi
 * @property string $relative_roi
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TradeSetup $entry
 * @property-read \App\Models\TradeSetup|null $exit
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Order[] $orders
 * @property-read int|null $orders_count
 * @method static \Illuminate\Database\Eloquent\Builder|Trade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Trade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Trade query()
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereEntryPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereEntryTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereExitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereExitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereExitTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereIsClosed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereIsStopped($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereMaxUsedSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereRelativeRoi($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereRoi($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereSide($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereTransactions($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Trade whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Trade extends Model
{
    use HasFactory;

    protected $casts = [
        'side'         => Side::class,
        'transactions' => 'array',
    ];

    public static function fromLoop(LiveTradeLoop $loop): static
    {
        if (!$position = $loop->status()->getPosition())
        {
            throw new \InvalidArgumentException('Can not create a model without a position.');
        }

        if ($position->isOpen())
        {
            throw new \InvalidArgumentException('Can not create a model from an open position.');
        }

        $model = new static;

        $model->side = $position->side;
        $model->is_stopped = $position->isStopped();
        $model->is_closed = $position->isClosed();
        $model->entry_time = $position->entryTime();
        $model->exit_time = $position->exitTime();
        $model->transactions = $position->transactionLog()->get();
        $model->max_used_size = $position->getMaxUsedSize();
        $model->entry_price = $position->getEntryPrice();
        $model->exit_price = $position->getExitPrice();
        $model->roi = $position->exitRoi();
        $model->relative_roi = $position->relativeExitRoi();

        $model->entry()->associate($loop->entry);

        if ($loop->hasExitTrade())
        {
            $model->exit()->associate($loop->exit);
        }

        \DB::transaction(function () use ($position, $model) {
            $model->save();
            foreach ($position->getOrders() as $order)
            {
                $order->position_id = $model->id;
                $order->save();
            }
        });

        return $model;
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TradeSetup::class, 'entry_id');
    }

    public function exit(): BelongsTo
    {
        return $this->belongsTo(TradeSetup::class, 'exit_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
