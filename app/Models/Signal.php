<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/** COLUMNS
 *
 * @property int    id
 * @property int    indicator_id
 * @property int    symbol_id
 * @property int    timestamp
 * @property string name
 * @property string side
 * @property bool   valid_price
 * @property string $signature_id
 * @property float  price
 * @property mixed  created_at
 * @property mixed  updated_at
 *
 * @property Symbol symbol
 */
class Signal extends Model
{
    use HasFactory;

    const BUY = 'BUY';
    const SELL = 'SELL';

    protected $table = 'signals';

    public function tradeSetup()
    {
        return $this->belongsToMany(TradeSetup::class);
    }

    public function symbol()
    {
        return $this->hasOne(Symbol::class);
    }

    public function toArray()
    {
        $result = parent::toArray();

        $result['price'] = round($result['price'], 2);

        return $result;
    }
}
