<?php

namespace App\Trade\Binding;

use App\Models\Binding;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @method morphMany(string $class, string $string)
 */
trait HasBinding
{
    /** @var Binding[] */
    public Collection $bindings;

    protected function initializeHasBinding(): void
    {
        $this->bindings = new Collection();
    }

    public function bindings(): MorphMany
    {
        return $this->morphMany(\App\Models\Binding::class, 'bindable');
    }
}