<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Medyat\Laraql\Source\Registry;
use Workbench\App\Laraql\OrderLineSource;
use Workbench\App\Laraql\OrderSource;
use Workbench\App\Models\Order;
use Workbench\App\Policies\OrderPolicy;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);

        $this->app->make(Registry::class)->register(OrderSource::class, OrderLineSource::class);
    }
}
