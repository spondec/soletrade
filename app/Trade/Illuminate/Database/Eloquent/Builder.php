<?php

namespace App\Trade\Illuminate\Database\Eloquent;

class Builder extends \Illuminate\Database\Eloquent\Builder
{
    public function updateOrCreate(array $attributes, array $values = [])
    {
        return $this->retryOnDuplicate(parent::updateOrCreate(...), $attributes, $values);
    }

    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        return $this->retryOnDuplicate(parent::firstOrCreate(...), $attributes, $values);
    }

    protected function retryOnDuplicate(\Closure $callback, ...$params): mixed
    {
        try
        {
            return $callback(...$params);
        } catch (\PDOException $e)
        {
            if (str_contains($e->getMessage(), 'Duplicate entry'))
            {
                return $callback(...$params);
            }
            throw $e;
        }
    }
}