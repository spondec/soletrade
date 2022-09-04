<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Created just to have a factory for testing
// Shouldn't be used in production to avoid massive Eloquent overhead

/**
 * App\Models\Candle
 *
 * @property int $id
 * @property int $symbol_id
 * @property int $t
 * @property string $o
 * @property string $c
 * @property string $h
 * @property string $l
 * @property string|null $v
 * @property-read \App\Models\Symbol $symbol
 * @method static \Database\Factories\CandleFactory factory(...$parameters)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle query()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereC($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereH($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereL($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereO($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereSymbolId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereT($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Candle whereV($value)
 * @mixin \Eloquent
 */
class Candle extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public $timestamps = false;

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }
}
