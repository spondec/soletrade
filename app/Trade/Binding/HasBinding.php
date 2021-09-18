<?php

namespace App\Trade\Binding;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @extends \App\Models\Model
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Binding[] bindings
 * @method morphMany(string $class, string $string)
 */
trait HasBinding
{
    public function bindings(): MorphMany
    {
        return $this->morphMany(\App\Models\Binding::class, 'bindable');
    }
}