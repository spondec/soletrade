<?php

namespace App\Models;

use App\Models\Exchange as ExchangeModel;
use App\Trade\Exchange\Exchange;

/**
 * @property int exchange_id
 */
trait HasExchange
{
    public function exchange(): Exchange
    {
        static $instances = [];

        if (isset($instances[$id = $this->exchange_id]))
        {
            return $instances[$id];
        }

        /** @var ExchangeModel $exchange */
        $exchange = ExchangeModel::query()->findOrFail($id);

        $account = ucfirst(mb_strtolower($exchange->account));
        $name = ucfirst(mb_strtolower($exchange->name));

        $class = '\App\Trade\Exchange\\' . "$account\\$name";

        if (!class_exists($class))
        {
            throw new \LogicException("Exchange instance '$name' couldn't be found at '$class'");
        }

        return $instances[$id] = $class::instance();
    }
}