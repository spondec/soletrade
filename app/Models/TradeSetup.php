<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int    id
 * @property int    position_id
 * @property int    signature_id
 * @property int    signal_count
 * @property int    timestamp
 * @property string hash
 * @property int    symbol_id
 * @property string name
 * @property string side
 * @property float  entry_price
 * @property float  close_price
 * @property float  stop_price
 * @property array  take_profits
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class TradeSetup extends \App\Models\Model
{
    use HasFactory;

    public array $signals;

//    protected $table = 'trade_setups';

    public function calculateRiskReward()
    {
        //TODO
    }

    public function signals()
    {
        return $this->belongsToMany(Signal::class);
    }

    public function takeProfits()
    {
        return $this->hasMany(TakeProfit::class);
    }
}
