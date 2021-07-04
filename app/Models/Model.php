<?php

namespace App\Models;

use Illuminate\Support\Facades\Validator;

abstract class Model extends \Illuminate\Database\Eloquent\Model
{
    const VALIDATION_RULES = [];

    public function validate(?array &$errors = null)
    {
        $errors = Validator::make($this->toArray(), static::VALIDATION_RULES)
            ->errors()
            ->messages();

        if ($errors)
        {
            foreach ($errors as $key => $error)
            {
                $errors[$key] = implode(' ,', $error);
            }

            throw new \UnexpectedValueException("Validation errors:\n" .
                implode("\n", $errors));
        }
    }

    protected static function booted()
    {
        parent::booted();

        static::saving(fn(self $model) => $model->validate());
    }

    public function setAttribute($key, $value)
    {
        if ($rules = static::VALIDATION_RULES[$key] ?? null)
        {
            $result = Validator::make([$key => $value], [$key => $rules]);

            if ($errors = $result->errors()->messages())
            {
                throw new \UnexpectedValueException(
                    'Validation error: ' . implode("\n", $errors[$key]));
            }
        }

        $this->attributes[$key] = $value;
    }
}