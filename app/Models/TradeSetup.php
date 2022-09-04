<?php

declare(strict_types=1);

namespace App\Models;

use App\Trade\Binding\HasBinding;
use App\Trade\Contract\Binding\Bindable;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Evaluation\Price;
use App\Trade\Order\Type\StopLimit;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rules\Enum;

/**
 * App\Models\TradeSetup
 *
 * @property int $id
 * @property int $symbol_id
 * @property int $signature_id
 * @property string $name
 * @property Side $side
 * @property float $price
 * @property float $size
 * @property string|null $target_price
 * @property string|null $stop_price
 * @property OrderType $entry_order_type
 * @property array|null $order_type_config
 * @property int $signal_count
 * @property array|null $signals
 * @property mixed|null $actions
 * @property int $is_permanent
 * @property int $timestamp
 * @property int $price_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Binding[] $bindings
 * @property-read int|null $bindings_count
 * @property-read \App\Models\Signature $signature
 * @property-read \App\Models\Symbol $symbol
 * @method static \Database\Factories\TradeSetupFactory factory(...$parameters)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup query()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereActions($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereCreatedAt($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereEntryOrderType($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereIsPermanent($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereName($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereOrderTypeConfig($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup wherePrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup wherePriceDate($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereSide($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereSignalCount($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereSignals($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereSignatureId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereSize($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereStopPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereSymbolId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereTargetPrice($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereTimestamp($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|TradeSetup whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TradeSetup extends Model implements Bindable
{
    use HasBinding;
    use HasFactory;

    public static function validationRules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'price_date' => 'gte:timestamp',
            'price'      => 'required|numeric',
            'side'       => ['required', new Enum(Side::class)]
        ];
    }

    protected function actions(): Attribute
    {
        return Attribute::make(
            get: fn(string|null $value): array => $value ?
                array_map(
                    fn(array $action) => new TradeAction($action),
                    json_decode($value, true)
                ) : [],
            set: fn(\JsonSerializable|array $value): string => json_encode($value)
        );
    }

    protected function signals(): Attribute
    {
        return Attribute::make(
            get: fn(string|null $value): array => $value ?
                array_map(
                    fn(array $signal) => new Signal($signal),
                    json_decode($value, true)
                ) : [],
            set: fn(\JsonSerializable|array $value): string => json_encode($value)
        );
    }

    protected $guarded = ['id'];
    protected $table = 'trade_setups';

    protected array $unique = ['symbol_id', 'signature_id', 'name', 'timestamp', 'side'];

    protected $attributes = [
        'size'         => 100,
        'target_price' => null,
        'stop_price'   => null,
        'signal_count' => 0,
        'is_permanent' => false,
    ];

    protected $casts = [
        'side'              => Side::class,
        'price'             => 'float',
        'order_type_config' => 'array',
        'entry_order_type'  => OrderType::class,
        'signals'           => 'array',
    ];

    public function loadBindingPrice(?Price $price, string $column, int $timestamp): void
    {
        if (!$price)
        {
            return;
        }

        $binding = $this->bindings[$column] ?? null;
        if ($binding && $entryPrice = $binding->getValue($timestamp))
        {
            $price->set($entryPrice, $timestamp, 'Binding: ' . $binding->name, true);
        }
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

        //TODO:: do this in a resource
        if (!empty($result['price']) || !empty($result['target_price']) || !empty($result['stop_price']))
        {
            $result['price'] = \round((float)$result['price'], 2);
            $result['target_price'] = $result['target_price'] ? \round((float)$result['target_price'], 2) : null;
            $result['stop_price'] = $result['stop_price'] ? \round((float)$result['stop_price'], 2) : null;
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
        if (!$price = $this->price)
        {
            throw new \UnexpectedValueException('Price is not set.');
        }

        return $price;
    }

    public function setStopPrice(float $ratio, float $triggerPriceRatio = StopLimit::DEFAULT_TRIGGER_PRICE_RATIO): void
    {
        if (!$ratio)
        {
            throw new \LogicException('Invalid ratio.');
        }

        $price = $this->assertPrice();

        if ($ratio <= $triggerPriceRatio)
        {
            throw new \LogicException('Trigger price ratio can not be less than or equal to the stop price percent.');
        }

        $this->fillJsonAttribute('order_type_config->' . OrderType::STOP_LIMIT->value . '->trigger_price_ratio', $triggerPriceRatio);

        $this->stop_price = $this->isBuy()
            ? $price - $price * \abs($ratio)
            : $price + $price * \abs($ratio);
    }

    public function setTargetPrice(float $ratio): void
    {
        if (!$ratio)
        {
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
