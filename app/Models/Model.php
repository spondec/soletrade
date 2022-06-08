<?php

namespace App\Models;

use Illuminate\Support\Facades\Validator;

abstract class Model extends \Illuminate\Database\Eloquent\Model
{
    protected array $unique = [];

    final public function validate(?array &$errors = null)
    {
        $errors = Validator::make($this->toArray(), static::validationRules())
            ->errors()
            ->messages();

        if ($errors) {
            foreach ($errors as $key => $error) {
                $errors[$key] = \implode(' ,', $error);
            }

            throw new \UnexpectedValueException("Validation errors:\n" .
                \implode("\n", $errors));
        }
    }

    public function findUnique(array $with = []): ?static
    {
        if ($this->unique) {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return static::query()
                ->with($with)
                ->where($this->uniqueAttributesToArray())
                ->first();
        }
        return null;
    }

    public function updateUniqueOrCreate(): static
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return static::query()->updateOrCreate(
            $this->uniqueAttributesToArray(),
            $this->attributesToArray()
        );
    }

    public function firstUniqueOrCreate(): static
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return static::query()->firstOrCreate(
            $this->uniqueAttributesToArray(),
            $this->attributesToArray()
        );
    }

    protected static function booted()
    {
        parent::booted();

        static::saving(static fn (self $model) => $model->validate());
    }

    public function uniqueAttributesToArray(): array
    {
        $attributes = [];
        foreach ($this->unique as $column) {
            $attributes[$column] = $this->getAttribute($column);
        }

        return $attributes;
    }

    public static function validationRules(): array
    {
        return [];
    }
}
