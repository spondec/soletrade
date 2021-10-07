<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Repositories\SymbolRepository;
use App\Trade\Evaluation\Summary;
use App\Trade\HasName;
use App\Trade\Log;
use App\Trade\StrategyTester;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    protected function getKeyByValue(string $name, array $items): ?string
    {
        if ($param = $this->request->get($name))
        {
            return array_search($param, $items);
        }
        return null;
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

        $symbol = $this->symbolRepo->fetchSymbol(exchange: $exchange::instance(), symbolName: $symbolName, interval: $interval);
        abort_if(!$symbol, 404, "Symbol $symbolName was not found.");

        if ($strategy)
        {
            $tester = new StrategyTester(App::make(SymbolRepository::class), [
                'strategy' => [
                    'startDate' => $start,
                    'endDate'   => $end
                ]
            ]);
            $result = $tester->runStrategy($symbol, $strategy);

            Log::execTimeStart('Evaluating and summarizing trades');
            /** @var Summary[]|Collection $tradeSummary */
            $tradeSummary = $result->trades()
                ->map(fn(Collection $trades): Summary => $tester->summary($trades));
            Log::execTimeFinish('Evaluating and summarizing trades');

//            Log::execTimeStart('Evaluating and summarizing signals');
//            /** @var Summary[]|Collection $signalSummary */
//            $signalSummary = $result->signals()
//                ->map(fn(Collection $signals): Summary => $tester->summary($signals));
//            Log::execTimeFinish('Evaluating and summarizing signals');

            Log::execTimeStart('Freshening evaluations');
            $signalWith = [
                'entry',
                'entry.bindings',
                'exit',
                'exit.bindings'
            ];

//            $this->freshenEvaluations($signalSummary, $signalWith);

            $tradeWith = array_merge($signalWith, [
                'entry.signals',
                'entry.signals.bindings',
                'exit.signals',
                'exit.signals.bindings'
            ]);

            $this->freshenEvaluations($tradeSummary, $tradeWith);
            Log::execTimeFinish('Freshening evaluations');

            Log::execTimeStart('Preparing symbol');
            $symbol = $symbol->toArray();
            $symbol['strategy'] = [
                'trades' => $tradeSummary->toArray(),
                //                'signals' => $signalSummary->toArray()
            ];
            Log::execTimeFinish('Preparing symbol');

            return $symbol;
        }

        $candles = $symbol->candles($range ? null : $limit, $start, $end);
        $this->symbolRepo->initIndicators($symbol, $candles, $indicators);

        return $symbol->toArray();
    }

    /**
     * @param Summary[] $summaries
     */
    protected function freshenEvaluations(Collection $summaries, array $with): void
    {
        foreach ($summaries as $summary)
        {
            $summary->evaluations(static function (Collection $evaluations) use ($with): Collection {
                return $evaluations->map(fn(Evaluation $e): Evaluation => $e->fresh($with));
            });
        }
    }
}
