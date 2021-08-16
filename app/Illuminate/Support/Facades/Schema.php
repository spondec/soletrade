<?php

namespace App\Illuminate\Support\Facades;

use App\Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class Schema extends \Illuminate\Support\Facades\Schema
{
    /**
     * Get a schema builder instance for a connection.
     *
     * @param  string|null  $name
     * @return Builder
     */
    public static function connection($name): Builder
    {
        /** @var \Illuminate\Database\Schema\Builder $builder */
        $builder = static::$app['db']->connection($name)->getSchemaBuilder();
        $builder->blueprintResolver(static function($table, $callback) {
            return new Blueprint($table, $callback);
        });
        return $builder;
    }

    /**
     * Get a schema builder instance for the default connection.
     *
     * @return Builder
     */
    protected static function getFacadeAccessor(): Builder
    {
        /** @var \Illuminate\Database\Schema\Builder $builder */
        $builder = static::$app['db']->connection()->getSchemaBuilder();
        $builder->blueprintResolver(static function($table, $callback) {
            return new Blueprint($table, $callback);
        });
        return $builder;
    }
}