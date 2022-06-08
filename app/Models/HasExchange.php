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
        static $cache = [];

        if (isset($cache[$id = $this->exchange_id]))
        {
            return $cache[$id];
        }

        /** @var ExchangeModel $exchange */
        $exchange = ExchangeModel::query()->findOrFail($id);
        $class = $exchange->class;

        if (!\class_exists($class))
        {
            throw new \LogicException("Class '$class' does not exist.");
        }

        if (!\is_subclass_of($class, Exchange::class))
        {
            throw new \LogicException("Class '$class' is not a subclass of '" . Exchange::class . "'");
        }

        return $cache[$id] = $class::instance();
    }
}
