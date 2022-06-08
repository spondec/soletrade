<?php

namespace App\Models;

use App\Trade\Enum\Side;
use App\Trade\Evaluation\LiveTradeLoop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int     id
 * @property int     entry_id
 * @property int     exit_id
 * @property Side    side
 * @property bool    is_stopped
 * @property bool    is_closed
 * @property int     entry_time
 * @property int     exit_time
 * @property array   transactions
 * @property float   max_used_size
 * @property float   entry_price
 * @property float   exit_price
 * @property float   roi
 * @property float   relative_roi
 *
 * @property Order[] orders
 */
class Trade extends Model
{
    use HasFactory;

    protected $casts = [
        'side'         => Side::class,
        'transactions' => 'array',
    ];

    public static function from(LiveTradeLoop $loop): static
    {
        if (!$position = $loop->status()->getPosition()) {
            throw new \InvalidArgumentException('Can not create a model without a position.');
        }

        if ($position->isOpen()) {
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

        if ($loop->hasExitTrade()) {
            $model->exit()->associate($loop->exit);
        }

        \DB::transaction(function () use ($position, $model) {
            $model->save();
            foreach ($position->getOrders() as $order) {
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
