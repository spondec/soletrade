<?php

namespace App\Models;

use App\Trade\Contract\Binding\Bindable;
use App\Trade\Contract\Binding\Binder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property Bindable                   bindable
 * @property Signature                  signature
 * @property int                        id
 * @property string                     bindable_type
 * @property int                        bindable_id
 * @property int                        signature_id
 * @property string                     class
 * @property string                     column
 * @property string                     name
 * @property \Illuminate\Support\Carbon created_at
 * @property \Illuminate\Support\Carbon updated_at
 */
class Binding extends Model
{
    protected $guarded = ['id'];
    protected array $unique = ['bindable_type', 'bindable_id', 'column'];

    public readonly Binder $binder;
    public readonly ?\Closure $callback;

    public function bindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function setBinder(Binder $binder, ?\Closure $callback): void
    {
        $this->binder = $binder;
        $this->callback = $callback;
    }

    public function getValue(int $timestamp)
    {
        $value = $this->binder->getBindValue($name = $this->name, $timestamp);
        $callbackParams = $this->binder->getExtraBindCallbackParams($name, $timestamp);

        return $this->callback ? ($this->callback)($value, ...$callbackParams) : $value;
    }
}
