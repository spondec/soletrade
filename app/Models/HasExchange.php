<?php

namespace App\Models;

use App\Trade\Exchange\AbstractExchange;

/**
 * @property int exchange_id
 */
trait HasExchange
{
    public function exchange(): AbstractExchange
    {
        static $instances = [];

        if (isset($instances[$id = $this->exchange_id]))
        {
            return $instances[$id];
        }

        /** @var Exchange $exchange */
        $exchange = Exchange::query()->findOrFail($id);

        $account = ucfirst(mb_strtolower($exchange->account));
        $name = ucfirst(mb_strtolower($exchange->name));

        $class = '\App\Trade\Exchange\\' . "$account\\$name";

        if (!class_exists($class))
        {
            throw new \LogicException("$name exchange instance couldn't be found at $class.");
        }

        return $instances[$id] = $class::instance();
    }
}