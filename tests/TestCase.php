<?php

declare(strict_types=1);

namespace Medyat\Laraql\Tests;

use Medyat\Laraql\LaraqlServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            LaraqlServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    /** SQLite in memory unless CI points DB_CONNECTION at PostgreSQL. */
    protected function defineEnvironment($app): void
    {
        if (! env('DB_CONNECTION')) {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite.database', ':memory:');
        }
    }
}
