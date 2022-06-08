<?php

namespace App\Trade\Illuminate\Support\Facades;

use App\Trade\Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class Schema extends \Illuminate\Support\Facades\Schema
{
    /**
     * Get a schema builder instance for a connection.
     *
     * @param string|null $name
     *
     * @return Builder
     */
    public static function connection($name): Builder
    {
        /** @var \Illuminate\Database\Schema\Builder $builder */
        $builder = parent::connection($name);
        $builder->blueprintResolver(static function ($table, $callback) {
            return new Blueprint($table, $callback);
        });

        return $builder;
    }
}
