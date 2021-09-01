<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int    id
 * @property int    position_id
 * @property int    signature_id
 * @property int    signal_count
 * @property int    timestamp
 * @property int    symbol_id
 * @property string name
 * @property string side
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

    protected $guarded = ['id'];
    protected $table = 'trade_setups';

    protected array $unique = ['symbol_id', 'signature_id', 'name', 'timestamp', 'side'];

    public function signals(): BelongsToMany
    {
        return $this->belongsToMany(Signal::class);
    }

    public function symbol()
    {
        return $this->belongsTo(Symbol::class);
    }

    public function takeProfits()
    {
        return $this->hasMany(TakeProfit::class);
    }

    public function isBuy()
    {
        return $this->side === Signal::BUY;
    }

    public function toArray()
    {
        $result = parent::toArray();

        $result['price'] = round($result['price'], 2);
        $result['close_price'] = round($result['close_price'] ?? 0, 2);
        $result['stop_price'] = round($result['stop_price'] ?? 0, 2);

        return $result;
    }

    public function setStopPrice(float $percent): void
    {
        $price = $this->price;

        if ($this->side === Signal::BUY)
        {
            $this->stop_price = $price - $price * $percent / 100;
        }
        else
        {
            $this->stop_price = $price + $price * $percent / 100;
        }
    }

    public function setClosePrice(float $percent): void
    {
        $price = $this->price;

        if ($this->side === Signal::BUY)
        {
            $this->close_price = $price + $price * $percent / 100;
        }
        else
        {
            $this->close_price = $price - $price * $percent / 100;
        }
    }
}
