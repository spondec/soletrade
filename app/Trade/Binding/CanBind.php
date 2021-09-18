<?php

namespace App\Trade\Binding;

use App\Models\Binding;
use App\Models\Model;

trait CanBind
{
    private ?array $bindable = null;
    private ?\WeakMap $bindings = null;

    /**
     * @param Model $model
     */
    protected function saveBindings(Bindable $model): void
    {
        if (!$model->exists)
        {
            throw new \LogicException('Model was not saved before binding.');
        }

        if ($bindings = $this->getBindings($model))
        {
            /** @var Binding $binding */
            foreach ($bindings as $column)
            {
                $binding = $column['binding'];
                $callback = $column['callback'];

                $binding->bindable()->associate($model);
                $this->preBindingSave($binding, $callback);
                $this->setBinding($model, $binding->updateUniqueOrCreate(), $callback);;
            }
        }
    }

    /**
     * @param Model $model
     *
     * @return Binding[]
     */
    protected function getBindings(Bindable $model): array
    {
        return $this->bindings[$model];
    }

    protected function preBindingSave(Binding $binding, ?\Closure $callback): void
    {

    }

    /**
     * @param Model $model
     */
    protected function setBinding(Bindable $model, Binding $binding, ?\Closure $callback): void
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

    protected function logChange(Binding $binding, int $timestamp): void
    {
        $history = $binding->history ?? [];

        if (in_array($value = $binding->value, $history))
        {
            return;
        }

        $history[$timestamp] = $value;
        $binding->history = $history;
    }

    protected function setHistory(Binding $binding, array $history, ?\Closure $callback): void
    {
        if ($callback)
        {
            foreach ($history as $key => $value)
            {
                $history[$key] = $callback($value);
            }
        }

        $binding->history = $history;
    }

    protected function getBindHistory(string|int $bind): ?array
    {
        return null;
    }

    final protected function syncBindings(Bindable $model): void
    {
        /** @var Binding $binding */
        foreach ($model->bindings()
                     ->where('class', static::class)
                     ->get() as $binding)
        {
            $this->bind($model,
                $column = $binding->column,
                $binding->name,
                $this->getCallback($model, $column));
        }
    }

    /**
     * @param Model $model
     */
    final public function bind(Bindable $model, string $column, string|int $bind, ?\Closure $callback = null): Binding
    {
        $this->assertBindExists($bind);
        $value = $this->getBindValue($bind);

        if ($callback)
        {
            $value = $this->runCallback($value, $callback);
        }

        $binding = $this->setupBinding($model, $column, $bind, $value);
        $this->setBinding($model, $binding, $callback);

        $model->setAttribute($column, $value);

        $this->afterBinding($binding, $callback);

        return $binding;
    }

    private function assertBindExists(string|int $bind): void
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

    protected function runCallback(mixed $value, \Closure $callback): mixed
    {
        $type = gettype($value);
        $value = $callback($value);

        if ($type !== gettype($value))
        {
            throw new \TypeError('Binding callback should not change the type of the value.');
        }
        return $value;
    }

    /**
     * @param Model $model
     */
    private function setupBinding(Bindable $model, string $column, string|int $bind, float $value): Binding
    {
        $binding = $this->getBinding($model, $column) ?? new Binding();
        $binding->column = $column;
        $binding->class = static::class;
        $binding->name = $bind;
        $binding->value = $value;

        return $binding;
    }

    /**
     * @param Model $model
     */
    protected function getBinding(Bindable $model, string $column): ?Binding
    {
        return $this->bindings[$model][$column]['binding'] ?? null;
    }

    protected function afterBinding(Binding $binding, ?\Closure $callback): void
    {

    }

    /**
     * @param Model $model
     */
    protected function getCallback(Bindable $model, string $column): ?\Closure
    {
        return $this->bindings[$model][$column]['callback'];
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

    abstract protected function getBindValue(int|string $bind): mixed;
}