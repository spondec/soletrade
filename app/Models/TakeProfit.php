<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/* @property int id
 * @property int   trade_setup_id
 * @property int   order_id
 * @property float percent
 * @property float price
 * @property bool  is_realized
 * @property mixed created_at
 * @property mixed updated_at
 */
class TakeProfit extends Model
{
    use HasFactory;

    protected $table = 'take_profits';

    public function setPercentAttribute(float $value)
    {
        if ($value <= 0 || $value > 100)
        {
            throw new \LogicException("Percent should be between %1 and %100. %$value given.");
        }

        $this->attributes['percent'] = $value;
    }

    public function tradeSetup()
    {
        return $this->belongsTo(TradeSetup::class);
    }


}
