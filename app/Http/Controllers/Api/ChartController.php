<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Symbol;
use App\Repositories\ConfigRepository;
use App\Repositories\SymbolRepository;
use App\Trade\Exchange\Exchange;
use App\Trade\HasName;
use App\Trade\Log;
use App\Trade\Strategy\Tester;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChartController extends Controller
{
    public function __construct(protected Request          $request,
                                protected SymbolRepository $symbolRepo,
                                protected ConfigRepository $config)
    {

    }

    public function index(Request $request): array
    {
        $symbol = $request->get('symbol');
        $exchange = $this->getKeyByValue('exchange', $this->mapByName($this->config->exchanges, true));
        $interval = $request->get('interval');

        if ($exchange && $symbol && $interval)
        {
            return $this->candles(
                exchange: $exchange,
                symbolName: $symbol,
                interval: $interval,
                indicators: $this->getSelectedIndicators($request),
                strategy: $this->getKeyByValue('strategy', $this->mapByName($this->config->strategies, true)),
                range: \json_decode($request->get('range'), true),
                limit: $request->get('limit'));
        }

        return [
            'strategies' => \array_keys(get_strategies()),
            'exchanges'  => $this->mapByName($this->config->exchanges),
            'symbols'    => $this->config->symbols,
            'indicators' => \array_keys(get_indicators()),
            'intervals'  => $this->symbolRepo->fetchIntervals()
        ];
    }

    protected function getKeyByValue(string $name, array $items): ?string
    {
        if ($param = $this->request->get($name))
        {
            return \array_search($param, $items);
        }
        return null;
    }

    /**
     * @param HasName[]|string[] $classes
     */
    protected function mapByName(array $classes, bool $classAsKey = false): array
    {
        $mapped = [];
        foreach ($classes as $class)
        {
            if ($classAsKey)
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

    public function candles(string|Exchange $exchange,
                            string          $symbolName,
                            string          $interval,
                            array           $indicators,
                            string          $strategy = null,
                            ?array          $range = null,
                            ?int            $limit = null): array
    {

        $start = $range ? as_ms(Carbon::parse($range['start'])->getTimestamp()) : null;
        $end = $range ? as_ms(Carbon::parse($range['end'])->getTimestamp()) : null;

        $symbol = $this->getSymbol($exchange, $symbolName, $interval);
        abort_if(!$symbol, 404, "Symbol $symbolName was not found.");

        if ($symbol->last_update <= $end)
        {
            $symbol->exchange()->update()->bySymbol($symbol);
        }

        if ($strategy)
        {
            $tester = new Tester($strategy, $symbol, [
                'startDate' => $start,
                'endDate'   => $end
            ]);

            $tester->strategy->updateSymbols();
            $trades = $tester->runStrategy();

            Log::execTimeStart('Evaluating trades');
            /** @var Collection $evaluations */
            $summary = $tester->summary($trades, $evaluations);
            Log::execTimeFinish('Evaluating trades');

            return [
                ...$symbol->toArray(),
                'strategy' => [
                    'trades' => [
                        'summary'     => $summary,
                        'evaluations' => $evaluations->map(fn(Evaluation $evaluation) => $evaluation->fresh())
                    ]
                ]
            ];
        }

        $symbol->updateCandlesIfOlderThan(60);
        $candles = $symbol->candles($range ? null : $limit, $start, $end);
        $this->symbolRepo->initIndicators($symbol, $candles, $indicators);

        return $symbol->toArray();
    }

    protected function getSymbol(Exchange|string $exchange, string $symbolName, string $interval): ?Symbol
    {
        return $this->symbolRepo->fetchSymbol(exchange: $exchange::instance(), symbolName: $symbolName, interval: $interval);
    }

    protected function getSelectedIndicators(Request $request): array
    {
        $indicators = get_indicators();
        $indicatorConfig = \json_decode($request->get('indicatorConfig', '{}'), true);
        return collect($request->get('indicators', []))
            ->mapWithKeys(function (string $name) use ($indicators, $indicatorConfig) {
                return [$indicators[$name] => $indicatorConfig[$name] ?? []];
            })->all();
    }
}
