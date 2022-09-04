<?php

namespace App\Models;

use App\Trade\Enum\Side;
use App\Trade\Indicator\Indicator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Signal
 *
 * @property-read \App\Models\Signature|null $indicator
 * @property-read \App\Models\Signature|null $signature
 * @property-read \App\Models\Symbol $symbol
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signal newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signal newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signal query()
 * @mixin \Eloquent
 */
class Signal extends Model
{
    public readonly Indicator $indicator;
    protected $table = 'signals';
    protected $guarded = [];
    protected array $unique = ['symbol_id', 'indicator_id', 'signature_id', 'timestamp'];
    protected $casts = [
        'info' => 'array'
    ];

    public static function validationRules(): array
    {
        return [
            'price_date' => 'gte:timestamp',
        ];
    }

    public function setIndicator(Indicator $indicator): void
    {
        $this->indicator = $indicator;
    }

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    public function buy(): static
    {
        $this->side = Side::BUY;
        return $this;
    }

    public function sell(): static
    {
        $this->side = Side::SELL;
        return $this;
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
}
