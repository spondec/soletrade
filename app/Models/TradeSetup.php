<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int    id
 * @property int    position_id
 * @property int    signature_id
 * @property int    signal_count
 * @property int    timestamp
 * @property int    symbol_id
 * @property string name
 * @property string side
 * @property bool   valid_price
 * @property float  price
 * @property float  close_price
 * @property float  stop_price
 * @property array  take_profits
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class TradeSetup extends \App\Models\Model
{
    use HasFactory;

    public array $signals = [];

//    protected $table = 'trade_setups';

    protected $guarded = ['id'];

    protected array $unique = ['symbol_id', 'signature_id', 'name', 'timestamp', 'side'];

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

    public function toArray()
    {
        $result = parent::toArray();

        $result['price'] = round($result['price'], 2);

        return $result;
    }
}
