<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Medyat\Laraql\Http\QueryController;

$path = config('laraql.route.path');
$middleware = config('laraql.route.middleware');

Route::post(is_string($path) ? $path : 'laraql', QueryController::class)
    ->middleware(is_array($middleware) ? array_values(array_filter($middleware, is_string(...))) : [])
    ->name('laraql.query');
