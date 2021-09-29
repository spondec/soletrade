<?php

namespace App\Trade\Binding;

use App\Models\Binding;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property Binding[] bindings
 */
interface Bindable
{
    public function bindings(): MorphMany;
}