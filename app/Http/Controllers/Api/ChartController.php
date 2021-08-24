<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\StrategyTester;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

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
            return $this->candles(exchange: $exchange,
                symbolName: $symbol,
                interval: $interval,
                indicators: $request->get('indicators', []),
                strategy: $request->get('strategy'),
                range: json_decode($request->get('range'), true),
                limit: $request->get('limit'));
        }

        return [
            'strategies' => Config::strategies(),
            'exchanges'  => Config::exchanges(),
            'symbols'    => Config::symbols(),
            'indicators' => Config::indicators(),
            'intervals'  => $this->symbolRepo->intervals()
        ];
    }

    /**
     * @param AbstractExchange $exchange
     */
    public function candles(string $exchange,
                            string $symbolName,
                            string $interval,
                            array  $indicators,
                            string $strategy = null,
                            ?array $range = null,
                            ?int   $limit = null): array
    {

        $start = $range ? Carbon::parse($range['start'])->getTimestampMs() : null;
        $end = $range ? Carbon::parse($range['end'])->getTimestampMs() : null;

        if ($strategy)
        {
            /** @var Symbol $symbol */
            $symbol = $this->symbolRepo->fetchSymbols(symbols: [$symbolName],
                interval: $interval,
                exchangeId: $exchange::instance()->id())
                ->first();

            abort_if(!$symbol, 404, "Symbol $symbolName was not found.");

            $tester = new StrategyTester(App::make(SymbolRepository::class), [
                'startDate' => $start,
                'endDate'   => $end
            ]);
            $result = $tester->run($strategy, $symbol);

            $symbol = $symbol->toArray();
            $symbol['strategy'] = $result;
            return $symbol;
        }

        return $this->symbolRepo->candles(exchange: $exchange::instance(),
                symbol: $symbolName,
                interval: $interval,
                limit: $range ? null : $limit,
                indicators: $indicators,
                end: $end,
                start: $start)?->toArray() ?? [];
    }
}
