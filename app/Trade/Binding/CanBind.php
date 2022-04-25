<?php

namespace App\Trade\Binding;

use App\Models\Binding;
use App\Models\Model;
use App\Trade\Contracts\Binding\Bindable;
use App\Trade\Contracts\Binding\Binder;

/** @implements Binder */
trait CanBind
{
    private ?\WeakMap $bindings = null;

    final public function bind(Bindable&Model $model,
                               string         $column,
                               string|int     $bind,
                               ?\Closure      $callback = null,
                               ?int           $timestamp = null): Binding
    {
        $this->assertBindExists($bind);

        $value = $this->getBindValue($bind, $timestamp);
        $params = $this->getExtraBindCallbackParams($bind, $timestamp);

        if ($callback)
        {
            $value = $callback($value, ...$params);
        }

        $binding = $this->setupBinding($model, $column, $bind);
        $this->setBinding($model, $binding, $callback);

        $model->setAttribute($column, $value);

        return $binding;
    }

    private function assertBindExists(string|int $bind): void
    {
        if ($bindable = $this->getBindable())
        {
            if (!\in_array($bind, $bindable))
            {
                throw new \InvalidArgumentException("$bind was not defined as a bindable.");
            }
        }
        else
        {
            throw new \LogicException('No bindable are defined.');
        }
    }

    private function setupBinding(Bindable&Model $model, string $column, string|int $bind): Binding
    {
        $binding = $this->getBinding($model, $column) ?? new Binding();
        $binding->column = $column;
        $binding->class = static::class;
        $binding->name = $bind;

        return $binding;
    }

    private function getBinding(Bindable&Model $model, string $column): ?Binding
    {
        return $this->bindings[$model][$column]['binding'] ?? null;
    }

    private function setBinding(Bindable&Model $model, Binding $binding, ?\Closure $callback): void
    {
        if (!$this->bindings)
        {
            $this->bindings = new \WeakMap();
        }

        if (!isset($this->bindings[$model]))
        {
            $this->bindings[$model] = [];
        }

        $column = $binding->column;

        $this->bindings[$model][$column]['binding'] = $binding;
        $this->bindings[$model][$column]['callback'] = $callback;
    }

    public function isBindable(mixed $bind): bool
    {
        return \in_array($bind, $this->getBindable());
    }

    public function saveBindings(Bindable&Model $model): void
    {
        if (!$model->exists)
        {
            throw new \LogicException('Model was not saved before binding.');
        }

        if ($bindings = $this->getBindings($model))
        {
            /** @var Binding $binding */
            foreach ($bindings as $column => $item)
            {
                $binding = $item['binding'];
                $callback = $item['callback'];

                $binding->bindable()->associate($model);
                $this->setBinding($model, $binding = $binding->updateUniqueOrCreate(), $callback);
                $binding->setBinder($this, $callback);
                $model->bindings[$column] = $binding;
            }
        }
    }

    /**
     * @return Binding[]|array
     */
    private function getBindings(Bindable&Model $model): ?array
    {
        return $this->bindings[$model] ?? null;
    }

    public function replaceBindable(Bindable&Model $current, Bindable&Model $new): void
    {
        if ($bindings = $this->bindings[$current] ?? false)
        {
            unset($this->bindings[$current]);
            $this->bindings[$new] = $bindings;
        }
    }

    /**
     * @return string[]
     */
    abstract public function getBindable(): array;
}