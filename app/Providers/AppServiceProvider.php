<?php

namespace App\Providers;

use App\Repositories\SymbolRepository;
use App\Trade\StrategyTester;
use App\Trade\Exchange\Spot\Binance;
use Illuminate\Database\Schema\Blueprint;
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
        try
        {
            Binance::instance();
        } catch (\Exception $e)
        {
            echo $e->getMessage();
        }
    }
}
