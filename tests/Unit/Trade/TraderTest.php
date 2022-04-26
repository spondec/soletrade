<?php

namespace Tests\Unit\Trade;

use App\Exceptions\PositionExitFailed;
use App\Models\Runner;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\ConfigRepository;
use App\Trade\Collection\TradeCollection;
use App\Trade\Contracts\Exchange\HasLeverage;
use App\Trade\Evaluation\LivePosition;
use App\Trade\Evaluation\TradeStatus;
use App\Trade\Exchange\Exchange;
use App\Trade\LiveTradeLoop;
use App\Trade\OrderManager;
use App\Trade\Status;
use App\Trade\Strategy\Strategy;
use App\Trade\TradeAsset;
use App\Trade\Trader;
use Mockery as m;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TraderTest extends m\Adapter\Phpunit\MockeryTestCase
{
    public function test_run_with_stopped_status(): void
    {
        $this->bypassOnShutdown();

        $trader = $this->getTrader();

        $trader->setStatus(Status::STOPPED);
        $this->assertNull($trader->run());
    }

    protected function bypassOnShutdown(): void
    {
        register_shutdown_function(static function () {
            exit;
        });
    }

    protected function getTrader(?Strategy   &$strategy = null,
                                 ?Exchange   &$exchange = null,
                                 ?Symbol     &$symbol = null,
                                 ?TradeAsset &$tradeAsset = null,
                                 ?Runner     &$runner = null,
                                 ?\App       &$app = null): Trader
    {
        $app = m::mock('overload:App');
        $app->shouldReceive('runningInConsole')->once()->andReturn(true);

        $runner = m::mock('overload:' . Runner::class);
        $runner->shouldReceive('delete');
        $runner->shouldReceive('query')->andReturnSelf();
        $runner->shouldReceive('first')->andReturn(null);
        $runner->shouldReceive('setExpiry')->andReturnSelf();
        $runner->shouldReceive('save');
        $runner->shouldReceive('purgeExpired')->once();

        $strategy = m::mock(Strategy::class);

        $symbol = m::mock('alias:' . Symbol::class);
        $symbol->symbol = 'BTC/USDT';

        $exchange = m::mock('overload:' . Exchange::class, HasLeverage::class);
        $exchange->shouldReceive('name')->andReturn('exchange');

        $tradeAsset = m::mock(TradeAsset::class);

        return new Trader($strategy, $exchange, $symbol, $tradeAsset);
    }

    public function test_set_leverage(): void
    {
        $this->bypassOnShutdown();

        /** @var m\MockInterface&Exchange $exchange */
        /** @var m\MockInterface&\App $app */
        /** @var m\MockInterface&Symbol $symbol */
        $trader = $this->getTrader(exchange: $exchange, symbol: $symbol, app: $app);
        $this->expectRecoverableRequest($app);
        $exchange->shouldReceive('setLeverage')->with(10, $symbol->symbol);
        $trader->setLeverage(10);
    }

    protected function expectRecoverableRequest(m\MockInterface&\App $app, array $handle = [\Throwable::class]): void
    {
        $configRepo = m::mock('alias:' . ConfigRepository::class);
        $configRepo->options = [
            'recoverableRequest' =>
                [
                    'retryInSeconds' => 1,
                    'retryLimit'     => 1,
                    'handle'         => $handle
                ]
        ];
        $app->shouldReceive('make')->with(ConfigRepository::class)->andReturn($configRepo);
    }

    public function test_run_with_active_status(): void
    {
        $this->bypassOnShutdown();

        /** @var m\MockInterface&Strategy $strategy */
        $trader = $this->getTrader(strategy: $strategy, symbol: $symbol, runner: $runner);

        $this->expectLoopRun($status, $loop);
        $status->shouldReceive('listen')->once();

        $trade = m::mock('alias:' . TradeSetup::class);
        $trade->id = 1;
        $trade->price_date = time();

        $this->expectStrategyRun($strategy, $symbol, [$trade]);
        $strategy->shouldReceive('evaluationSymbol')->once()->andReturn($symbol);
        $strategy->shouldReceive('config')->once()->with('evaluation.loop');

        \Closure::bind(function () use ($trade) {
            return $this->runner->start_date = $trade->price_date - 1;
        }, $trader, $trader)();

        $trader->setStatus(Status::AWAITING_TRADE);
        $trader->run();
    }

    protected function expectLoopRun(TradeStatus &$status = null, LiveTradeLoop &$loop = null): void
    {
        $status = m::mock(TradeStatus::class);

        $loop = m::mock('overload:' . LiveTradeLoop::class);
        $loop->order = m::mock(OrderManager::class);

        $loop->shouldReceive('status')->andReturn($status);
        $loop->shouldReceive('run')->once()->andReturn($status);
    }

    /**
     * @param Strategy&m\MockInterface $strategy
     * @param                          $symbol
     * @param TradeSetup[]             $trades
     *
     * @return void
     */
    protected function expectStrategyRun(m\MockInterface&Strategy $strategy, $symbol, array $trades): void
    {
        $strategy->shouldReceive('run')
            ->with($symbol)
            ->once()
            ->andReturn(new TradeCollection($trades));
    }

    public function test_run_with_exit_trade(): void
    {
        $this->bypassOnShutdown();

        /** @var m\MockInterface&Strategy $strategy */
        $trader = $this->getTrader(strategy: $strategy, symbol: $symbol, runner: $runner);

        /** @var m\MockInterface&LiveTradeLoop $loop */
        /** @var m\MockInterface&TradeStatus $status */
        $this->expectLoopRun($status, $loop);

        $entry = m::mock('alias:' . TradeSetup::class);
        $entry->price_date = $entry->timestamp = time();
        $entry->id = 1;
        $entry->shouldReceive('isBuy')->andReturn(true);

        $exit = m::mock('alias:' . TradeSetup::class);
        $exit->price_date = $exit->timestamp = $entry->price_date + 1;
        $exit->id = 1;
        $exit->shouldReceive('isBuy')->andReturn(false);

        $status->shouldReceive('isEntered')->once()->andReturn(true);
        $loop->shouldReceive('setExitTrade')->once()->with($exit);
        $loop->shouldReceive('hasExitTrade')->once()->andReturn(true);

        \Closure::bind(function () use ($entry, $loop) {
            $this->runner->start_date = $entry->price_date - 1;
            $this->loop = $loop;
            /** @noinspection PhpReadonlyPropertyWrittenOutsideDeclarationScopeInspection */
            $this->loop->entry = $entry;
        }, $trader, $trader)();

        $this->expectStrategyRun($strategy, $symbol, [$entry, $exit]);

        $trader->setStatus(Status::AWAITING_TRADE);
        $trader->run();
    }

    public function test_end_loop(): void
    {
        $trader = $this->getTrader(symbol: $symbol, runner: $runne, app: $app);
        $trader->setStatus(Status::AWAITING_TRADE);

        \Closure::bind(function () {
            $this->loop = m::mock('alias:' . LiveTradeLoop::class);
            /** @noinspection PhpReadonlyPropertyWrittenOutsideDeclarationScopeInspection */
            $this->loop->order = m::mock('alias:' . OrderManager::class);
            $this->loop->order->shouldReceive('cancelAll')->once();
            $this->loop->order->shouldReceive('syncAll')->times(2);
            $status = m::mock('alias:' . TradeStatus::class);
            $this->loop->shouldReceive('status')->once()->andReturn($status);
            $position = m::mock('alias:' . LivePosition::class);
            $status->shouldReceive('getPosition')->once()->andReturn($position);
            $position->shouldReceive('isOpen')->times(3)->andReturn(true);
            $position->shouldReceive('stop')->once();
        }, $trader, $trader)();

        m::mock('overload:' . \Log::class)->shouldReceive('error')->once();
        $this->expectException(PositionExitFailed::class);
        $this->expectRecoverableRequest($app, [PositionExitFailed::class]);

        $trader->setStatus(Status::STOPPED);
    }
}
