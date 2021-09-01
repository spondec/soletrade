<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int               id
 * @property string            type
 * @property int               entry_id
 * @property TradeSetup|Signal entry
 * @property TradeSetup|Signal exit
 * @property int               exit_id
 * @property float             realized_roi
 * @property float             highest_roi
 * @property float             lowest_roi
 * @property string            side
 * @property float             highest_price
 * @property float             lowest_price
 * @property float             highest_entry_price
 * @property float             lowest_entry_price
 * @property bool              is_entry_price_valid
 * @property bool              is_ambiguous
 * @property bool              is_stopped
 * @property bool              is_closed
 * @property int               entry_timestamp
 * @property int               exit_timestamp
 * @property \Carbon\Carbon    created_at
 * @property \Carbon\Carbon    updated_at
 */
class Evaluation extends Model
{
    use HasFactory;

    protected array $unique = ['type', 'entry_id', 'exit_id'];

    public function entry(): MorphTo
    {
        return $this->morphTo('entry', 'type');
    }

    public function exit(): MorphTo
    {
        return $this->morphTo('exit', 'type');
    }

    public function getExitPrice(): float
    {
        if ($this->is_stopped)
        {
            return $this->entry->stop_price;
        }

        if ($this->is_closed)
        {
            return $this->entry->close_price;
        }

        return $this->exit->price;
    }
}
