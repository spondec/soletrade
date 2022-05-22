<?php

namespace App\Providers;

use App\Illuminate\Database\Schema\Blueprint;
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Trade\Repository\ConfigRepository;
use App\Trade\Repository\SymbolRepository;
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
        if (!App::runningInConsole())
        {
            \set_time_limit(30);
        }
        \ini_set('trader.real_precision', 10);

        $this->app->singleton(SymbolRepository::class);
        $this->app->singleton(ConfigRepository::class);

        $this->app->bind('db.schema', static function ($app) {
            $builder = $app['db']->connection()->getSchemaBuilder();
            $builder->blueprintResolver(static function ($table, $callback) {
                return new Blueprint($table, $callback);
            });

            return $builder;
        });
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
