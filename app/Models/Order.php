<?php

declare(strict_types=1);

namespace App\Models;

use App\Trade\Side;
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
 * @property bool        is_open
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
     * @var \Closure[]
     */
    static protected array $fillListeners = [];

    static protected array $fills = [];

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

    public static function validationRules(): array
    {
        return [
            'exchange_id'      => 'required|integer|exists:exchanges,id',
            'symbol'           => 'required|string|max:50',
            'is_open'          => 'boolean',
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

    public static function newFill(Fill $fill)
    {
        static::$fills[$fill->order_id][$fill->id] = $fill;

        foreach (static::$fillListeners[$fill->order_id] ?? [] as $callback)
        {
            $callback($fill);
        }
    }

    public static function hasListener(Fill $fill): bool
    {
        return !empty(static::$fillListeners[$fill->order_id]);
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

    public function setAttribute($key, $value)
    {
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
    }
}
