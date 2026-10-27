<?php

declare(strict_types=1);

namespace Medyat\Laraql;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;

/** Per-request facts every query in a batch shares. */
final readonly class Context
{
    public function __construct(
        public string $timezone,
        public CarbonImmutable $now,
        public ?Authenticatable $user = null,
    ) {}
}
