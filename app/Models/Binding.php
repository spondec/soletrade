<?php

namespace App\Models;

use App\Trade\Binding\Bindable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property Bindable                                   bindable
 * @property SavePoint[]|\Illuminate\Support\Collection savePoints
 * @property Signature                                  signature
 *
 * @property int                                        id
 * @property string                                     bindable_type
 * @property int                                        bindable_id
 * @property int                                        signature_id
 * @property string                                     class
 * @property string                                     column
 * @property string                                     name
 *
 * @property \Illuminate\Support\Carbon                 created_at
 * @property \Illuminate\Support\Carbon                 updated_at
 */
class Binding extends Model
{
    protected $guarded = ['id'];
    protected array $unique = ['bindable_type', 'bindable_id', 'column'];

    public function bindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function savePoints(): HasMany
    {
        return $this->hasMany(SavePoint::class, 'binding_signature_id', 'signature_id');
    }
}
