<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use Workbench\App\Models\User;

/** The fixture user "guest" is the one without the ability (error-forbidden). */
class OrderPolicy
{
    public function viewAny(?User $user): bool
    {
        return $user?->name !== 'guest';
    }
}
