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
use Illuminate\Support\Facades\App;

class ChartController extends Controller
{
    public function __construct(protected Request          $request,
                                protected SymbolRepository $symbolRepo,
                                protected ConfigRepository $configRepo)
    {

    }

    public function index(Request $request): array
    {
        $symbol = $request->get('symbol');
        $exchange = $this->getKeyByValue('exchange',
            $this->mapClassByName($this->configRepo->exchanges, true));
        $interval = $request->get('interval');

        if ($exchange && $symbol && $interval)
        {
            $indicators = $this->mapClassByName($this->configRepo->indicators, true);
            return $this->candles(
                exchange: $exchange,
                symbolName: $symbol,
                interval: $interval,
                indicators: \array_map(static fn($v) => \array_search($v, $indicators), $request->get('indicators', [])),
                strategy: $this->getKeyByValue('strategy', $this->mapClassByName($this->configRepo->strategies, true)),
                range: \json_decode($request->get('range'), true, 512, JSON_THROW_ON_ERROR),
                limit: $request->get('limit'));
        }

        return [
            'strategies' => $this->mapClassByName($this->configRepo->strategies),
            'exchanges'  => $this->mapClassByName($this->configRepo->exchanges),
            'symbols'    => $this->configRepo->symbols,
            'indicators' => $this->mapClassByName($this->configRepo->indicators),
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
    protected function mapClassByName(array $classes, bool $assoc = false): array
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

    public function candles(string|Exchange $exchange,
                            string          $symbolName,
                            string          $interval,
                            array           $indicators,
                            string          $strategy = null,
                            ?array          $range = null,
                            ?int            $limit = null): array
    {

        $start = $range ? Carbon::parse($range['start'])->getTimestampMs() : null;
        $end = $range ? Carbon::parse($range['end'])->getTimestampMs() : null;

        $symbol = $this->getSymbol($exchange, $symbolName, $interval);

        if (!$symbol)
        {
            $filter = static fn(Symbol $symbol): bool => $symbol->symbol == $symbolName && $symbol->interval == $interval;
            $symbol = $exchange::instance()->update()->byInterval(interval: $interval, filter: $filter)?->first();
        }

        abort_if(!$symbol, 404, "Symbol $symbolName was not found.");

        if ($symbol->last_update <= $end)
        {
            $symbol->exchange()->update()->bySymbol($symbol);
        }

        if ($strategy)
        {
            $tester = new Tester(App::make(SymbolRepository::class), $strategy, [
                'startDate' => $start,
                'endDate'   => $end
            ]);

            $trades = $tester->runStrategy($symbol);

            Log::execTimeStart('Evaluating and summarizing trades');

            $summary = $tester->summary($trades);
            $summary['evaluations'] = $summary['evaluations']->map(
                static fn(Evaluation $evaluation): Evaluation => $evaluation->fresh());

            Log::execTimeFinish('Evaluating and summarizing trades');

            Log::execTimeStart('Preparing symbol');

            $symbol = $symbol->toArray();
            $symbol['strategy'] = ['trades' => $summary];

            Log::execTimeFinish('Preparing symbol');

            return $symbol;
        }

        $symbol->updateCandlesIfOlderThan(60);
        $candles = $symbol->candles($range ? null : $limit, $start, $end);
        $this->symbolRepo->initIndicators($symbol, $candles, $indicators);

        return $symbol->toArray();
    }

    /**
     * @param Exchange|string $exchange
     * @param string          $symbolName
     * @param string          $interval
     *
     * @return Symbol|null
     */
    protected function getSymbol(Exchange|string $exchange, string $symbolName, string $interval): ?Symbol
    {
        return $this->symbolRepo->fetchSymbol(exchange: $exchange::instance(), symbolName: $symbolName, interval: $interval);
    }
}
