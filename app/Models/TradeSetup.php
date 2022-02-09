<?php

declare(strict_types=1);

namespace App\Models;

use App\Trade\Binding\Bindable;
use App\Trade\Binding\HasBinding;
use App\Trade\Evaluation\Price;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Signal[]|\Illuminate\Database\Eloquent\Collection      signals
 * @property Symbol                                                 symbol
 * @property Signature                                              signature
 * @property TradeAction[]|\Illuminate\Database\Eloquent\Collection actions
 *
 * @property int                                                    id
 * @property int                                                    position_id
 * @property int                                                    signature_id
 * @property int                                                    signal_count
 * @property int                                                    timestamp
 * @property int                                                    price_date
 * @property int                                                    symbol_id
 * @property string                                                 name
 * @property string                                                 side
 * @property float                                                  price
 * @property float                                                  size
 * @property float                                                  close_price
 * @property float                                                  stop_price
 * @property array                                                  take_profits
 * @property mixed                                                  created_at
 * @property mixed                                                  updated_at
 */
class TradeSetup extends Model implements Bindable
{
    use HasBinding;

    protected $guarded = ['id'];
    protected $table = 'trade_setups';

    protected array $unique = ['symbol_id', 'signature_id', 'name', 'timestamp', 'side'];

    protected $attributes = [
        'size'        => 100,
        'close_price' => null,
        'stop_price'  => null,
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

    public function symbol()
    {
        return $this->belongsTo(Symbol::class);
    }

    public function signature()
    {
        return $this->belongsTo(Signature::class);
    }

    public function toArray()
    {
        $result = parent::toArray();
        $result['price'] = \round((float)$result['price'], 2);
        $result['close_price'] = $result['close_price'] ? \round((float)$result['close_price'], 2) : null;
        $result['stop_price'] = $result['stop_price'] ? \round((float)$result['stop_price'], 2) : null;

        return $result;
    }

    public function isBuy()
    {
        return $this->side === Signal::BUY;
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
