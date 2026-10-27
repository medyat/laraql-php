<?php

declare(strict_types=1);

namespace Medyat\Laraql;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Medyat\Laraql\Console\GlossaryCommand;
use Medyat\Laraql\Glossary\Glossary;
use Medyat\Laraql\Query\Validator;
use Medyat\Laraql\Source\Registry;
use Medyat\Laraql\Support\Raw;

class LaraqlServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraql.php', 'laraql');

        $this->app->singleton(Registry::class);

        $this->app->singleton(Validator::class, function (Application $app): Validator {
            return new Validator($app->make(Registry::class), Raw::assoc($app->make('config')->get('laraql.limits')));
        });

        $this->app->singleton(Glossary::class, function (Application $app): Glossary {
            return new Glossary($app->make(Registry::class), Raw::assoc($app->make('config')->get('laraql.limits')));
        });

        $this->app->singleton(Laraql::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/laraql.php');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laraql');

        $sources = $this->app->make('config')->get('laraql.sources');

        if (is_array($sources) && $sources !== []) {
            /** @var list<class-string<Source\Source>> $classes */
            $classes = array_values(array_filter($sources, is_string(...)));
            $this->app->make(Registry::class)->register(...$classes);
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laraql.php' => config_path('laraql.php'),
        ], ['laraql', 'laraql-config']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/laraql'),
        ], ['laraql', 'laraql-lang']);

        $this->commands([GlossaryCommand::class]);
    }
}
