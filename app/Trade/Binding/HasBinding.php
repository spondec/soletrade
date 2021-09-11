<?php

namespace App\Trade\Binding;

use App\Models\Binding;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @extends \App\Models\Model
 * @method morphMany(string $class, string $string)
 */
trait HasBinding
{
    public function bindings(): MorphMany
    {
        return $this->morphMany(Binding::class, 'bindable');
    }
}