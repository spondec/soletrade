<?php

declare(strict_types=1);

namespace App\Models;

use App\Trade\Binding\HasBinding;
use App\Trade\Contract\Binding\Bindable;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Evaluation\Price;
use App\Trade\Order\Type\StopLimit;
use Database\Factories\TradeSetupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rules\Enum;

/**
 * @property Signal[]|\Illuminate\Database\Eloquent\Collection      signals
 * @property Symbol                                                 symbol
 * @property Signature                                              signature
 * @property TradeAction[]|\Illuminate\Database\Eloquent\Collection actions
 * @property int                                                    id
 * @property int                                                    symbol_id
 * @property int                                                    signature_id
 * @property int                                                    signal_count
 * @property bool                                                   is_permanent
 * @property int                                                    timestamp
 * @property int                                                    price_date
 * @property string                                                 name
 * @property Side                                                   side
 * @property OrderType                                              entry_order_type
 * @property array                                                  order_type_config
 * @property float                                                  price
 * @property float                                                  size
 * @property float                                                  target_price
 * @property float                                                  stop_price
 * @property mixed                                                  created_at
 * @property mixed                                                  updated_at
 *
 * @method static TradeSetupFactory factory($count = null, $state = [])
 */
class TradeSetup extends Model implements Bindable
{
    use HasBinding;
    use HasFactory;

    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price_date' => 'gte:timestamp',
            'price' => 'required|numeric',
            'side' => ['required', new Enum(Side::class)],
        ];
    }

    protected $guarded = ['id'];
    protected $table = 'trade_setups';

    protected array $unique = ['symbol_id', 'signature_id', 'name', 'timestamp', 'side'];

    protected $attributes = [
        'size' => 100,
        'target_price' => null,
        'stop_price' => null,
        'signal_count' => 0,
        'is_permanent' => false,
    ];

    protected $casts = [
        'side' => Side::class,
        'price' => 'float',
        'order_type_config' => 'array',
        'entry_order_type' => OrderType::class,
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(TradeAction::class);
    }

    public function loadBindingPrice(?Price $price, string $column, int $timestamp): void
    {
        if (!$price) {
            return;
        }

        $binding = $this->bindings[$column] ?? null;
        if ($binding && $entryPrice = $binding->getValue($timestamp)) {
            $price->set($entryPrice, $timestamp, 'Binding: '.$binding->name, true);
        }
    }

    public function signals(): BelongsToMany
    {
        return $this->belongsToMany(Signal::class);
    }

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function toArray()
    {
        $result = parent::toArray();

        if (!empty($result['price']) || !empty($result['target_price']) || !empty($result['stop_price'])) {
            $result['price'] = \round((float) $result['price'], 2);
            $result['target_price'] = $result['target_price'] ? \round((float) $result['target_price'], 2) : null;
            $result['stop_price'] = $result['stop_price'] ? \round((float) $result['stop_price'], 2) : null;
        }

        return $result;
    }

    public function isBuy(): bool
    {
        return $this->side()->isBuy();
    }

    public function side(): Side
    {
        return $this->side;
    }

    protected function assertPrice(): float
    {
        if (!$price = $this->price) {
            throw new \UnexpectedValueException('Price is not set.');
        }

        return $price;
    }

    public function setStopPrice(float $ratio, float $triggerPriceRatio = StopLimit::DEFAULT_TRIGGER_PRICE_RATIO): void
    {
        if (!$ratio) {
            throw new \LogicException('Invalid ratio.');
        }

        $price = $this->assertPrice();

        if ($ratio <= $triggerPriceRatio) {
            throw new \LogicException('Trigger price ratio can not be less than or equal to the stop price percent.');
        }

        $this->fillJsonAttribute('order_type_config->'.OrderType::STOP_LIMIT->value.'->trigger_price_ratio', $triggerPriceRatio);

        $this->stop_price = $this->isBuy()
            ? $price - $price * \abs($ratio)
            : $price + $price * \abs($ratio);
    }

    public function setTargetPrice(float $ratio): void
    {
        if (!$ratio) {
            throw new \LogicException('Invalid ratio.');
        }

        $price = $this->assertPrice();

        $this->target_price = $this->isBuy()
            ? $price + $price * \abs($ratio)
            : $price - $price * \abs($ratio);
    }

    public function setSide(Side $side): void
    {
        $this->side = $side->value;
    }
}
