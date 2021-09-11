<?php

namespace App\Models;

use App\Trade\Binding\Bindable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property Bindable                   bindable
 *
 * @property int                        id
 * @property string                     bindable_type
 * @property int                        bindable_id
 * @property string                     class
 * @property string                     column
 * @property float                      value
 * @property string                     name
 * @property array                      history
 *
 * @property \Illuminate\Support\Carbon created_at
 * @property \Illuminate\Support\Carbon updated_at
 */
class Binding extends Model
{
    protected $guarded = ['id'];
    protected array $unique = ['bindable_type', 'bindable_id', 'column'];
    protected $casts = ['history' => 'array'];

    public function bindable(): MorphTo
    {
        return $this->morphTo();
    }
}
