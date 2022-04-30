<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int   total
 * @property int   ambiguous
 * @property int   profit
 * @property int   loss
 * @property int   failed
 * @property float fee_ratio
 * @property float total_fee
 * @property array balance_history
 * @property float avg_roi
 * @property float avg_profit_roi
 * @property float avg_loss_roi
 * @property float avg_highest_roi
 * @property float avg_lowest_roi
 * @property float success_ratio
 * @property float roi
 * @property float risk_reward_ratio
 */
class Summary extends Model
{
    protected $casts = [
        'balance_history' => 'array'
    ];

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
