<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Http\Request;

class ExchangeController extends Controller
{
    public function index(): array
    {
        return array_map(fn($v) => $v::instance()->info(), Config::exchanges());
    }

    public function symbols(string $exchange): array
    {
        if (in_array($exchange, Config::exchanges()))
        {
            /** @var AbstractExchange $exchange */
            $instance = $exchange::instance();

            return $instance->symbols();
        }

        throw new \HttpException("Exchange $exchange doesn't exist.");
    }
}
