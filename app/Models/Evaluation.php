<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Evaluation
 *
 * @property int $id
 * @property string $type
 * @property int $entry_id
 * @property int|null $exit_id
 * @property int $symbol_id
 * @property float|null $relative_roi
 * @property float|null $highest_roi
 * @property float|null $lowest_roi
 * @property float $used_size
 * @property string|null $entry_price
 * @property string|null $avg_entry_price
 * @property string|null $exit_price
 * @property string|null $target_price
 * @property string|null $stop_price
 * @property string|null $highest_price
 * @property string|null $lowest_price
 * @property string|null $highest_entry_price
 * @property string|null $lowest_entry_price
 * @property int $is_entry_price_valid
 * @property int|null $is_ambiguous
 * @property int|null $is_stopped
 * @property int|null $is_closed
 * @property int|null $entry_timestamp
 * @property int|null $exit_timestamp
 * @property array|null $log
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $entry
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $exit
 * @property-read \App\Models\Symbol $symbol
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation query()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereAvgEntryPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereCreatedAt($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereEntryId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereEntryPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereEntryTimestamp($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereExitId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereExitPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereExitTimestamp($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereHighestEntryPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereHighestPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereHighestRoi($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereIsAmbiguous($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereIsClosed($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereIsEntryPriceValid($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereIsStopped($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereLog($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereLowestEntryPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereLowestPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereLowestRoi($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereRelativeRoi($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereStopPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereSymbolId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereTargetPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereType($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereUpdatedAt($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Evaluation whereUsedSize($value)
 * @mixin \Eloquent
 */
class Evaluation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $with = ['entry', 'exit'];
    protected array $unique = ['type', 'entry_id', 'exit_id'];
    protected $casts = [
        'log' => 'array'
    ];

    public function isExited(): bool
    {
        return (bool)$this->exit_timestamp;
    }

    public function entry(): MorphTo
    {
        return $this->morphTo('entry', 'type');
    }

    public function exit(): MorphTo
    {
        return $this->morphTo('exit', 'type');
    }

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }
}
