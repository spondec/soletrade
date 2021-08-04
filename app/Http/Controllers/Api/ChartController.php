<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\SymbolRepository;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChartController extends Controller
{
    public function __construct(protected SymbolRepository $symbolRepo)
    {
    }

    public function index(Request $request): array
    {
        $symbol = $request->get('symbol');
        $exchange = $request->get('exchange');
        $interval = $request->get('interval');

        if ($exchange && $symbol && $interval)
        {
            return $this->candles($exchange,
                $symbol,
                $interval,
                $request->get('indicators', []),
                $request->get('before'),
                $request->get('limit'));
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
    public function candles(string $exchange,
                            string $symbol,
                            string $interval,
                            array  $indicators,
                            ?int   $before = null,
                            ?int   $limit = null): array
    {
        return $this->symbolRepo->candles($exchange::instance(),
                $symbol,
                $interval,
                $before,
                $limit,
                $indicators)?->toArray() ?? [];
    }
}
