<?php

namespace App\Trade\Binding;

use App\Models\Binding;
use App\Models\Model;

trait CanBind
{
    protected ?\WeakMap $bindings = null;
    private ?array $bindable = null;
    private ?\Closure $callback = null;

    /**
     * @param Model $model
     */
    protected function saveBindings(Bindable $model, int $timestamp)
    {
        if (!$model->exists)
        {
            throw new \LogicException('Model was not saved before binding.');
        }

        if ($bindings = $this->bindings[$model] ?? null)
        {
            /** @var Binding $binding */
            foreach ($bindings as $binding)
            {
                $binding->bindable()->associate($model);
                $this->logChange($binding, $timestamp);
                $binding->updateUniqueOrCreate();
            }
        }
    }

    protected function logChange(Binding $binding, int $timestamp): void
    {
        $history = $binding->history ?? [];
        $history[$timestamp] = $binding->value;
        $binding->history = $history;
    }

    protected final function syncBindings(Bindable $model): void
    {
        /** @var Binding $binding */
        foreach ($model->bindings()
                     ->where('class', static::class)
                     ->get() as $binding)
        {
            $this->bind($model, $binding->column, $binding->name, $this->callback);
        }
    }

    /**
     * @param Model $model
     */
    public final function bind(Bindable $model, string $column, string $bind, ?\Closure $callback = null): void
    {
        $this->assertBindExists($bind);
        $value = $this->getBindValue($bind);

        if ($callback)
        {
            if (!$this->callback)
            {
                $this->callback = $callback;
            }
            $value = $callback($value);
        }

        if (!$this->bindings)
        {
            $this->bindings = new \WeakMap();
        }

        if (!isset($this->bindings[$model]))
        {
            $this->bindings[$model] = [];
        }

        $this->bindings[$model][$column] = $this->setupBinding($model, $column, $bind, $value);

        $model->setAttribute($column, $value);
    }

    protected function assertBindExists(string $bind): void
    {
        if ($this->bindable === null)
        {
            $this->bindable = $this->getBindable();
        }

        if ($this->bindable)
        {
            if (!in_array($bind, $this->bindable))
            {
                throw  new \InvalidArgumentException("$bind was not defined as a bindable.");
            }
        }
        else
        {
            throw new \LogicException('No bindable are defined.');
        }
    }

    /**
     * @param Model $model
     */
    protected function setupBinding(Bindable $model, string $column, string $bind, float $value): Binding
    {
        $binding = $this->bindings[$model][$column] ?? new Binding();
        $binding->column = $column;
        $binding->class = static::class;
        $binding->name = $bind;
        $binding->value = $value;

        return $binding;
    }

    protected function replaceBindable(Bindable $current, Bindable $new): void
    {
        if ($bindings = $this->bindings[$current])
        {
            unset($this->bindings[$current]);
            $this->bindings[$new] = $bindings;
        }
    }

    /**
     * @return string[]
     */
    abstract protected function getBindable(): array;

    abstract protected function getBindValue(int|float|string $bind): mixed;
}