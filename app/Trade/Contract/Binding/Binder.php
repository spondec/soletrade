<?php

namespace App\Trade\Contract\Binding;

use App\Models\Binding;
use App\Models\Model;

interface Binder
{
    public function getBindValue(int|string $bind, ?int $timestamp = null): mixed;

    public function isBindable(mixed $bind): bool;

    public function bind(Bindable & Model $model,
                         string $column,
                         string|int $bind,
                         ?\Closure $callback = null,
                         ?int $timestamp = null): Binding;

    /**
     * @return string[]
     */
    public function getBindable(): array;

    public function getExtraBindCallbackParams(int|string $bind, ?int $timestamp = null): array;
}
