<?php

declare(strict_types=1);

namespace Medyat\Laraql;

use Illuminate\Support\ServiceProvider;

class LaraqlServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraql.php', 'laraql');

        $this->app->singleton(Laraql::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/laraql.php');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laraql');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laraql.php' => config_path('laraql.php'),
        ], ['laraql', 'laraql-config']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/laraql'),
        ], ['laraql', 'laraql-lang']);
    }
}
