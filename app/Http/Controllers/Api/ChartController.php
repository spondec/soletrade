<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Model;
use App\Models\Symbol;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChartController extends Controller
{
    public function index(Request $request): array
    {
        $symbol = $request->get('symbol');
        $exchange = $request->get('exchange');
        $interval = $request->get('interval');

        if ($exchange && $symbol && $interval)
        {
            return $this->candles($exchange, $symbol, $interval);
        }

        return [
            'exchanges'  => Config::exchanges(),
            'symbols'    => Config::symbols(),
            'indicators' => Config::indicators(),
            'intervals'  => DB::table('symbols')->distinct()->get('interval')->pluck('interval')
        ];
    }

    /**
     * @param AbstractExchange $exchange
     */
    public function candles(string $exchange, string $symbol, string $interval, ?int $before = null): array
    {
        /** @var Symbol $symbol */
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $symbol = Symbol::query()
            ->where('exchange_id', $exchange::instance()->id())
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->first();

        if ($before)
        {
            $symbol->candles($before);
        }

        return $symbol?->toArray() ?? [];
    }
}
