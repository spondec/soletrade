<?php

namespace App\Models;

use App\Trade\Indicator\Indicator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** COLUMNS
 *
 * @property Symbol    symbol
 * @property Signature signature
 * @property Signature indicator
 *
 * @property int       id
 * @property int       indicator_id
 * @property int       symbol_id
 * @property int       timestamp
 * @property int       price_date
 * @property bool      is_confirmed
 * @property string    name
 * @property string    side
 * @property string    signature_id
 * @property float     price
 * @property array     info
 * @property mixed     created_at
 * @property mixed     updated_at
 *
 */
class Signal extends Model
{
    public const VALIDATION_RULES = [
        'price_date' => 'gte:timestamp',
    ];

    public readonly Indicator $indicator;

    public function setIndicator(Indicator $indicator): void
    {
        $this->indicator = $indicator;
    }

    const BUY = 'BUY';
    const SELL = 'SELL';

    protected $table = 'signals';

    protected $guarded = ['id'];
    protected array $unique = ['symbol_id', 'indicator_id', 'signature_id', 'timestamp'];

    protected $casts = [
        'info' => 'array'
    ];

    public function tradeSetup(): BelongsToMany
    {
        return $this->belongsToMany(TradeSetup::class);
    }

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    //TODO:: rename this
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $result['price'] = \round($result['price'], 2);

        return $result;
    }
}
