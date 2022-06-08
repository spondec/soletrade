<?php

declare(strict_types=1);

namespace App\Models;

use App\Trade\Enum\OrderStatus;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Log;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;

/**
 * @property int         id
 * @property int         exchange_id
 * @property int         position_id
 * @property bool        reduce_only
 * @property string      symbol
 * @property Side        side
 * @property OrderType   type
 * @property OrderStatus status
 * @property float       quantity
 * @property float       filled
 * @property float       price
 * @property float       stop_price
 * @property array       responses
 * @property float       commission
 * @property string      commission_asset
 * @property string      exchange_order_id
 * @property Carbon      created_at
 * @property Carbon      updated_at
 *
 * @method static OrderFactory factory($count = null, $state = [])
 */
class Order extends Model
{
    use HasFactory;

    /**
     * @var \Closure[][]
     */
    static protected array $fillListeners = [];
    /**
     * @var Fill[][]
     */
    static protected array $fills = [];
    /**
     * @var \Closure[][]
     */
    static protected array $cancelListeners = [];

    protected $table = 'orders';
    protected $casts = [
        'responses' => 'array',
        'type'      => OrderType::class,
        'side'      => Side::class,
        'status'    => OrderStatus::class,
    ];

    protected $attributes = [
        'filled' => 0,
    ];

    protected static function booted()
    {
        parent::booted();

        static::saved(static function (self $order)
        {
            if ($order->status === OrderStatus::CANCELED)
            {
                static::handleCancel($order);
            }
        });
    }

    protected static function handleCancel(Order $order): void
    {
        foreach (static::$cancelListeners[$order->id] ?? [] as $cancelListener)
        {
            $cancelListener($order);
        }
    }

    public function onCancel(\Closure $callback): void
    {
        if (!$this->exists)
        {
            throw new \LogicException('Cannot attach listener to non-existing order.');
        }

        static::$cancelListeners[$this->id][] = $callback;
    }

    public function isOpen(): bool
    {
        return \in_array($this->status, [OrderStatus::OPEN, OrderStatus::NEW]);
    }

    public function flushListeners(): void
    {
        if ($this->isOpen())
        {
            throw new \LogicException('Cannot flush listeners for an open order.');
        }

        Log::info('Flushing listeners for order #' . $this->id);
        unset(static::$fillListeners[$this->id]);
        unset(static::$fills[$this->id]);
        unset(static::$cancelListeners[$this->id]);
    }

    public static function validationRules(): array
    {
        return [
            'exchange_id'      => 'required|integer|exists:exchanges,id',
            'symbol'           => 'required|string|max:50',
            'reduce_only'      => 'boolean',
            'quantity'         => 'required|numeric|gt:0',
            'filled'           => 'numeric',
            'price'            => 'nullable|numeric|gt:0',
            'commission'       => 'nullable|numeric|gt:0',
            'commission_asset' => 'nullable|string|max:50',
            'stop_price'       => 'nullable|numeric',
            'type'             => [new Enum(OrderType::class)],
            'side'             => [new Enum(Side::class)],
            'status'           => [new Enum(OrderStatus::class)]
        ];
    }

    public static function newFill(Fill $fill): void
    {
        static::$fills[$fill->order_id][$fill->id] = $fill;

        foreach (static::$fillListeners[$fill->order_id] ?? [] as $callback)
        {
            $callback($fill);
        }

        Log::info(\count(static::$fills[$fill->order_id]) . ' fills for order ' . $fill->order_id);
        Log::info(\count(static::$fills) . ' total fills');
    }

    public function isAllFilled(): bool
    {
        return $this->rawFills()->sum('quantity') == $this->quantity;
    }

    public function rawFills(): Builder
    {
        if (!$this->exists)
        {
            throw new \LogicException('Order is not saved.');
        }

        return \DB::table('fills')
            ->where('order_id', $this->id);
    }

    public function fills(): HasMany
    {
        return $this->hasMany(Fill::class);
    }

    public function avgFillPrice(): float
    {
        return (float)$this
            ->rawFills()
            ->selectRaw('SUM(quantity * price) / SUM(quantity) as avgPrice')
            ->first()
            ->avgPrice;
    }

    public function logResponse(string $key, array $data): void
    {
        $responses = $this->responses ?? [];

        if (!isset($responses[$key]) || \end($responses[$key]) != $data)
        {
            $responses[$key][] = $data;
        }

        $this->responses = $responses;
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function onFill(\Closure $callback): void
    {
        static::$fillListeners[$this->id][] = $callback;

        foreach (static::$fills[$this->id] ?? [] as $fill)
        {
            //run the listener if it registered not before but after the fill, so they won't be missed
            //happens with immediate order fills
            $callback($fill);
        }

        Log::info(\count(static::$fillListeners[$this->id]) . ' fill listeners registered for order ' . $this->id);
        Log::info(\count(static::$fillListeners) . ' total fill listeners registered');
    }
}
