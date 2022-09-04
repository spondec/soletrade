<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Summary
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Summary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Summary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Summary query()
 * @mixin \Eloquent
 */
class Summary extends Model
{
    protected $casts = [
        'balance_history' => 'array'
    ];

    public array $parameters = [];

    protected $attributes = [
        'roi'               => 0,
        'avg_roi'           => 0,
        'avg_profit_roi'    => 0,
        'avg_loss_roi'      => 0,
        'risk_reward_ratio' => 0,
        'loss'              => 0,
        'profit'            => 0,
        'failed'            => 0,
        'ambiguous'         => 0
    ];
}
