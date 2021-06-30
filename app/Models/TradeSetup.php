<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    id
 * @property int    position_id
 * @property string name
 * @property string symbol
 * @property string side
 * @property float  entry_price
 * @property float  close_price
 * @property float  stop_price
 * @property float  potential_rrr
 * @property float  realized_rrr
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class TradeSetup extends Model
{
    use HasFactory;

//    protected $table = 'trade_setups';

    public function calculateRiskReward()
    {
        //TODO
    }
}
