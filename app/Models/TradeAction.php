<?php

namespace App\Models;

/**
 * @property int    id
 * @property int    trade_setup_id
 * @property string class
 * @property bool   is_taken
 * @property int    timestamp
 * @property array  config
 */
class TradeAction extends Model
{
    protected $casts = [
        'config' => 'array'
    ];
    protected $guarded = ['id'];
}
