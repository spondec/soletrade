<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int    id
 * @property int    exchange_id
 * @property int    trade_setup_id
 * @property bool   is_open
 * @property string exchange
 * @property string account
 * @property string symbol
 * @property string side
 * @property string type
 * @property string status
 * @property float  quantity
 * @property float  filled
 * @property float  price
 * @property float  stop_price
 * @property array  responses
 * @property float  commission
 * @property string commission_asset
 * @property string exchange_order_id
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class Order extends Model
{
    use HasFactory;

    const VALIDATION_RULES = [
        'exchange_id' => 'required|integer|exists:exchanges,id',
        'symbol'      => 'required|string|max:50',
        'is_open'     => 'boolean',
        'quantity'    => 'required|numeric|gt:0',
        'filled'      => 'numeric',
        'order_id'    => 'exists:fills',
        'price'       => 'numeric|gt:0',
        'stop_price'  => 'nullable|numeric',
        'type'        => 'required|in:LIMIT,MARKET,STOP_LOSS,STOP_LOSS_LIMIT,TAKE_PROFIT,TAKE_PROFIT_LIMIT,LIMIT_MAKER',
        'side'        => 'required|in:BUY,SELL,LONG,SHORT',
        'status'      => 'required|in:CLOSED,OPEN,EXPIRED,NEW,PENDING_CANCEL,REJECTED,CANCELED,PARTIALLY_FILLED'
    ];
    /**
     * @var \Closure[]
     */
    static protected array $fillListeners = [];
    protected $table = 'orders';
    protected $casts = [
        'responses' => 'array'
    ];

    public static function newFill(Fill $fill)
    {
        foreach (self::$fillListeners[$fill->order_id] ?? [] as $callback)
        {
            $callback($fill);
        }
    }

    public function setAttribute($key, $value)
    {
        if (\in_array($key, ['side', 'type', 'status', 'exchange', 'account']))
        {
            $value = \mb_strtoupper($value);
        }

        parent::setAttribute($key, $value);
    }

    public function logResponse(string $key, array $data): void
    {
        $responses = $this->responses ?? [];

        $lastKey = \array_key_last($responses);

        if ($lastKey === null || ($key !== $lastKey && $data !== $responses[$lastKey]))
        {
            $responses[$key][] = $data;
            $this->responses = $responses;
        }
    }

    public function onFill(\Closure $callback): void
    {
        self::$fillListeners[$this->id][] = $callback;
    }
}
