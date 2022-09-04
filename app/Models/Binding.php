<?php

namespace App\Models;

use App\Trade\Contract\Binding\Binder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Binding
 *
 * @property int $id
 * @property string $bindable_type
 * @property int $bindable_id
 * @property string $column
 * @property string $class
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $bindable
 * @property-read \App\Models\Signature|null $signature
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding query()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereBindableId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereBindableType($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereClass($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereColumn($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereCreatedAt($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereName($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Binding whereUpdatedAt($value)
 * @mixin \Eloquent
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
