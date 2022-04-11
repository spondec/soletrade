<?php

declare(strict_types=1);

namespace App\Models;

use App\Trade\Binding\HasBinding;
use App\Trade\Contracts\Binding\Bindable;
use App\Trade\Evaluation\Price;
use App\Trade\Order\Type\StopLimit;
use App\Trade\Side;
use Database\Factories\TradeSetupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Signal[]|\Illuminate\Database\Eloquent\Collection      signals
 * @property Symbol                                                 symbol
 * @property Signature                                              signature
 * @property TradeAction[]|\Illuminate\Database\Eloquent\Collection actions
 *
 * @property int                                                    id
 * @property int                                                    symbol_id
 * @property int                                                    signature_id
 * @property int                                                    signal_count
 * @property int                                                    timestamp
 * @property int                                                    price_date
 * @property string                                                 name
 * @property string                                                 side
 * @property OrderType                                              entry_order_type
 * @property array                                                  order_type_params
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
            'price_date' => 'gte:timestamp',
        ];
    }

    protected $guarded = ['id'];
    protected $table = 'trade_setups';

    protected array $unique = ['symbol_id', 'signature_id', 'name', 'timestamp', 'side'];

    protected $attributes = [
        'size'         => 100,
        'target_price' => null,
        'stop_price'   => null,
    ];

    protected $casts = [
        'price'             => 'float',
        'order_type_params' => 'array',
        'entry_order_type'  => OrderType::class,
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(TradeAction::class);
    }

    public function loadBindingPrice(?Price $price, string $column, int $timestamp, ...$params): void
    {
        if ($price && !$price->isLocked())
        {
            $binding = $this->bindings[$column] ?? null;
            if ($binding && $entryPrice = $binding->getBindValue($timestamp, ...$params))
            {
                $price->set($entryPrice, $timestamp, 'Binding: ' . $binding->name);
            }
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
        $result['price'] = \round((float)$result['price'], 2);
        $result['target_price'] = $result['target_price'] ? \round((float)$result['target_price'], 2) : null;
        $result['stop_price'] = $result['stop_price'] ? \round((float)$result['stop_price'], 2) : null;

        return $result;
    }

    public function isBuy(): bool
    {
        return $this->side()->isBuy();
    }

    public function side(): Side
    {
        return Side::from($this->side);
    }

    protected function assertPrice(): float
    {
        if (!$price = $this->price)
        {
            throw new \UnexpectedValueException('Price is not set.');
        }

        return $price;
    }

    public function setStopPrice(float $percent, float $stopPriceRatio = StopLimit::DEFAULT_STOP_PRICE_RATIO): void
    {
        $price = $this->assertPrice();

        if ($stopPriceRatio < $percent / 100)
        {
            throw new \LogicException('Stop price ratio can not be less than stop price percent.');
        }

        $this->fillJsonAttribute('order_type_params->stop_price_ratio', $stopPriceRatio);

        if ($percent)

            if ($this->isBuy())
            {
                $this->stop_price = $price - $price * $percent / 100;
            }
            else
            {
                $this->stop_price = $price + $price * $percent / 100;
            }
    }

    public function setTargetPrice(float $percent): void
    {
        $price = $this->assertPrice();

        if ($this->isBuy())
        {
            $this->target_price = $price + $price * $percent / 100;
        }
        else
        {
            $this->target_price = $price - $price * $percent / 100;
        }
    }

    public function setSide(Side $side): void
    {
        $this->side = $side->value;
    }
}
