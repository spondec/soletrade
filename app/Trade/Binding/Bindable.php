<?php

namespace App\Trade\Binding;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property Bindable binding
 */
interface Bindable
{
    public function bindings(): MorphMany;
}