<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Symbol;
use App\Repositories\SymbolRepository;
use App\Trade\HasName;
use App\Trade\StrategyTester;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ChartController extends Controller
{
    public function __construct(protected Request $request, protected SymbolRepository $symbolRepo)
    {

    }

    public function index(Request $request): array
    {
        $symbol = $request->get('symbol');
        $exchange = $this->getKeyByValue('exchange', $this->mapClassByName(Config::exchanges(), true));
        $interval = $request->get('interval');

        if ($exchange && $symbol && $interval)
        {
            $indicators = $this->mapClassByName(Config::indicators(), true);
            return $this->candles(exchange: $exchange,
                symbolName: $symbol,
                interval: $interval,
                indicators: array_map(fn($v) => array_search($v, $indicators), $request->get('indicators', [])),
                strategy: $this->getKeyByValue('strategy', $this->mapClassByName(Config::strategies(), true)),
                range: json_decode($request->get('range'), true),
                limit: $request->get('limit'));
        }

        return [
            'strategies' => $this->mapClassByName(Config::strategies()),
            'exchanges'  => $this->mapClassByName(Config::exchanges()),
            'symbols'    => Config::symbols(),
            'indicators' => $this->mapClassByName(Config::indicators()),
            'intervals'  => $this->symbolRepo->intervals()
        ];
    }

    /**
     * @param HasName[]|string[] $classes
     */
    protected function mapClassByName(array $classes, bool $assoc = false)
    {
        $mapped = [];
        foreach ($classes as $class)
        {
            if ($assoc)
            {
                $mapped[$class] = $class::name();
            }
            else
            {
                $mapped[] = $class::name();
            }
        }

        return $mapped;
    }

    protected function getKeyByValue(string $name, array $items): ?string
    {
        if ($param = $this->request->get($name))
            return array_search($param, $items);
        return null;
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
