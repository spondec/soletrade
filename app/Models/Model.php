<?php

namespace App\Models;

use Illuminate\Support\Facades\Validator;

abstract class Model extends \Illuminate\Database\Eloquent\Model
{
    const VALIDATION_RULES = [];

    protected array $unique = [];

    public final function validate(?array &$errors = null)
    {
        $errors = Validator::make($this->toArray(), static::VALIDATION_RULES)
            ->errors()
            ->messages();

        if ($errors)
        {
            foreach ($errors as $key => $error)
            {
                $errors[$key] = \implode(' ,', $error);
            }

            throw new \UnexpectedValueException("Validation errors:\n" .
                \implode("\n", $errors));
        }
    }

    public function findUnique(array $with = []): ?static
    {
        if ($this->unique)
        {
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
            $this->uniqueAttributesToArray(), $this->attributesToArray());
    }

    protected static function booted()
    {
        parent::booted();

        static::saving(static fn(self $model) => $model->validate());
    }

    public function setAttribute($key, $value)
    {
        if ($rules = static::VALIDATION_RULES[$key] ?? null)
        {
            $result = Validator::make([$key => $value], [$key => $rules]);

            if ($errors = $result->errors()->messages())
            {
                throw new \UnexpectedValueException(
                    'Validation error: ' . \implode("\n", $errors[$key]));
            }
        }

        parent::setAttribute($key, $value);
    }

    public function uniqueAttributesToArray(): array
    {
        $attributes = [];
        foreach ($this->unique as $column)
        {
            $attributes[$column] = $this->getAttribute($column);
        }

        return $attributes;
    }
}