<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Model;
use App\Repositories\BindingRepository;
use App\Trade\Binding\Bindable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class SavePointAccess
{
    protected Collection $savePoints;
    protected BindingRepository $repo;

    /**
     * @param Model $model
     */
    public function __construct(protected Bindable $model, int $startDate, int $endDate)
    {
        $this->repo = App::make(BindingRepository::class);
        $this->savePoints = new Collection();

        foreach ($model->bindings as $binding)
        {
            $this->savePoints[$binding->column] = $this->repo->fetchSavePoints($binding, $startDate, $endDate);
        }
    }

    public function lastPointOrAttribute(string $column, int $timestamp): float
    {
        return $this->lastPoint($column, $timestamp) ?? $this->model->getAttribute($column);
    }

    public function lastPoint(string $column, int $timestamp): ?float
    {
        $value = null;

        if (!isset($this->savePoints[$column]))
        {
            return $value;
        }

        foreach ($this->savePoints[$column] as $savePoint)
        {
            if ($savePoint->timestamp <= $timestamp)
            {
                $value = $savePoint->value;
            }
        }

        if ($value)
        {
            return (float)$value;
        }

        return $value;
    }
}