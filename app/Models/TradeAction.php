<?php

namespace App\Models;

/**
 * App\Models\TradeAction
 *
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeAction newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeAction newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeAction query()
 * @mixin \Eloquent
 */
class TradeAction extends Model
{
    protected $casts = [
        'config' => 'array'
    ];
    protected $guarded = [];
}
