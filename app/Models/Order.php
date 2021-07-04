<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int    id
 * @property int    trade_setup_id
 * @property bool   is_open
 * @property string exchange
 * @property string account
 * @property string symbol
 * @property string side
 * @property string type
 * @property float  quantity
 * @property float  filled
 * @property float  price
 * @property float  stop_price
 * @property array  request
 * @property array  response
 * @property float  commission
 * @property string commission_asset
 * @property string exchange_order_id
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $attributes = [
        'request' => [],
        'response' => []
    ];

    protected $casts = [
        'request' => 'array',
        'response' => 'array'
    ];

    const VALIDATION_RULES = [
        'exchange' => 'required|in:BINANCE,FTX',
        'account' => 'required|in:SPOT,FUTURES',
        'symbol' => 'required|string|max:50',
        'is_open' => 'boolean',
        'quantity' => 'required|numeric',
        'filled' => 'numeric',
        'price' => 'numeric',
        'stop_price' => 'required|numeric',
        'type' => 'required|in:LIMIT,MARKET,STOP_LOSS,STOP_LOSS_LIMIT,TAKE_PROFIT,TAKE_PROFIT_LIMIT,LIMIT_MAKER',
        'side' => 'required|in:BUY,SELL,LONG,SHORT'
    ];

    public function logRequest(string $key, array $data)
    {
        $this->request[$key][] = $data;
    }

    public function logResponse(string $key, array $data)
    {
        $this->response[$key][] = $data;
    }
}
