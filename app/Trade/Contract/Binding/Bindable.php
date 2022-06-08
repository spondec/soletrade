<?php

namespace App\Trade\Contract\Binding;

use App\Models\Binding;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @property Collection|Binding[] bindings
 */
interface Bindable
{
    public function bindings(): MorphMany;
}