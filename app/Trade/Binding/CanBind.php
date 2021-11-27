<?php

namespace App\Trade\Binding;

use App\Models\Binding;
use App\Models\Model;
use App\Models\Signature;
use App\Repositories\BindingRepository;
use App\Trade\HasSignature;
use Illuminate\Support\Facades\App;

trait CanBind
{
    use HasSignature;

    private ?array $bindable = null;
    private ?\WeakMap $bindings = null;

    private array $handledCache = [];

    private BindingRepository $bindingRepo;

    /**
     * @param Model $model
     *
     * @throws \Exception
     */
    final public function bind(Bindable   $model,
                               string     $column,
                               string|int $bind,
                               ?\Closure  $callback = null,
                               ?int       $timestamp = null): Binding
    {
        $this->assertBindExists($bind);
        $this->bindingRepo = App::make(BindingRepository::class);

        $value = $this->getBindValue($bind, $timestamp);

        $signature = $this->register([
            'bind'     => $bind,
            'callback' => $callback,
            'extra'    => $this->getBindingSignatureExtra($bind)
        ]);

        $this->handleSavePoints($signature, $bind, $callback);

        if ($callback)
        {
            $value = $callback($value);
        }

        $binding = $this->setupBinding($model, $column, $bind, $signature);
        $this->setBinding($model, $binding, $callback);

        $model->setAttribute($column, $value);

        return $binding;
    }

    public function isBindable(mixed $bind): bool
    {
        return in_array($bind, $this->bindable);
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

    protected function handleSavePoints(Signature $signature, string|int $bind, ?\Closure $callback): void
    {
        if (!isset($this->handledCache[$id = $signature->id]))
        {
            if ($points = $this->getSavePoints(bind: $bind, signature: $signature))
            {
                $points = $this->processSavePoints($points, $id, $callback);

                $this->bindingRepo->insertSavePoints($points);
            }

            $this->handledCache[$id] = true;
        }
    }

    private function processSavePoints(array $points, int $signatureId, ?\Closure $callback): array
    {
        if ($callback)
        {
            foreach ($points as $key => $point)
            {
                $points[$key]['value'] = $callback($point['value']);
                $points[$key]['binding_signature_id'] = $signatureId;
            }
        }
        else
        {
            foreach ($points as $key => $point)
            {
                $points[$key]['binding_signature_id'] = $signatureId;
            }
        }

        return $points;
    }

    /**
     * @param Model $model
     */
    private function setupBinding(Bindable $model, string $column, string|int $bind, Signature $signature): Binding
    {
        $binding = $this->getBinding($model, $column) ?? new Binding();
        $binding->column = $column;
        $binding->class = static::class;
        $binding->name = $bind;
        $binding->signature()->associate($signature);

        return $binding;
    }

    /**
     * @param Model $model
     */
    private function getBinding(Bindable $model, string $column): ?Binding
    {
        return $this->bindings[$model][$column]['binding'] ?? null;
    }

    /**
     * @param Model $model
     */
    private function setBinding(Bindable $model, Binding $binding, ?\Closure $callback): void
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

    protected function getBindingSignatureExtra(string|int $bind): array
    {
        return [];
    }

    abstract protected function getSavePoints(string|int $bind, Signature $signature): array;

    /**
     * @param Model $model
     */
    public function saveBindings(Bindable $model): void
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
                $this->setBinding($model, $binding->updateUniqueOrCreate(), $callback);
            }
        }
    }

    /**
     * @param Model $model
     *
     * @return Binding[]
     */
    private function getBindings(Bindable $model): ?array
    {
        return $this->bindings[$model] ?? null;
    }

    public function replaceBindable(Bindable $current, Bindable $new): void
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
    abstract protected function getBindable(): array;

    abstract protected function getBindValue(int|string $bind, ?int $timestamp = null): mixed;
}