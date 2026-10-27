<?php

declare(strict_types=1);

namespace Medyat\Laraql\Tests;

use Medyat\Laraql\LaraqlServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaraqlServiceProvider::class,
        ];
    }
}
