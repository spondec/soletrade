<?php

namespace App\Providers;

use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\StrategyTester;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!App::runningInConsole()) set_time_limit(30);
        ini_set('trader.real_precision', 10);
        $this->app->singleton(StrategyTester::class);
        $this->app->singleton(SymbolRepository::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Relation::enforceMorphMap([
            'signal'      => Signal::class,
            'trade_setup' => TradeSetup::class,
        ]);
    }
}
