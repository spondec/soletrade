<?php

namespace App\Models;

use App\Trade\Side;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;

/**
 * @property int    id
 * @property int    exchange_id
 * @property int    trade_setup_id
 * @property bool   is_open
 * @property bool   reduce_only
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

    public static function validationRules(): array
    {
        return [
            'exchange_id' => 'required|integer|exists:exchanges,id',
            'symbol'      => 'required|string|max:50',
            'is_open'     => 'boolean',
            'reduce_only' => 'boolean',
            'quantity'    => 'required|numeric|gt:0',
            'filled'      => 'numeric',
            'order_id'    => 'exists:fills',
            'price'       => 'numeric|gt:0',
            'stop_price'  => 'nullable|numeric',
            'type'        => [new Enum(OrderType::class)],
            'side'        => [new Enum(Side::class)],
            'status'      => [new Enum(OrderStatus::class)]
        ];
    }

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
        foreach (static::$fillListeners[$fill->order_id] ?? [] as $callback)
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
        static::$fillListeners[$this->id][] = $callback;
    }
}
