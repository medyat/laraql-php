<?php

declare(strict_types=1);

use Medyat\Laraql\Laraql;

it('resolves the singleton', function () {
    expect(app(Laraql::class))->toBeInstanceOf(Laraql::class);
});

it('returns the same instance from the container', function () {
    expect(app(Laraql::class))->toBe(app(Laraql::class));
});

it('merges the package config', function () {
    expect(config('laraql.route.path'))->toBe('laraql');
    expect(config('laraql.limits.page_size.max'))->toBe(500);
});

it('loads the package translations', function () {
    expect(trans('laraql::messages.unknown_source', ['source' => 'order']))->toBe('Unknown source "order".');
});
