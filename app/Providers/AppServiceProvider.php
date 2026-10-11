<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ([\App\MarketData\Contracts\MarketDataProvider::class, \App\MarketData\Contracts\HistoricalCandleProvider::class, \App\MarketData\Contracts\LiveTradeStreamProvider::class, \App\MarketData\Contracts\MarketTickerProvider::class] as $contract) { $this->app->bind($contract, \App\MarketData\BinanceSpotProvider::class); }
        $this->app->bind(\App\MarketData\Contracts\MarketDataHealthProvider::class, \App\MarketData\FeedHealth::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $prohibit = !\App\Support\DatabaseSafety::isolatedTests();
        foreach ([\Illuminate\Database\Console\WipeCommand::class, \Illuminate\Database\Console\Migrations\FreshCommand::class, \Illuminate\Database\Console\Migrations\RefreshCommand::class, \Illuminate\Database\Console\Migrations\ResetCommand::class, \Illuminate\Database\Console\Migrations\RollbackCommand::class] as $command) {
            $command::prohibit($prohibit);
        }
        config(['broadcasting.connections.reverb.client_options.timeout'=>5,'broadcasting.connections.reverb.client_options.connect_timeout'=>2]);
        //
    }
}
