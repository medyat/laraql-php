<?php

declare(strict_types=1);

namespace Medyat\Laraql\Exceptions;

final class ForbiddenException extends LaraqlException
{
    public function __construct()
    {
        parent::__construct(403, 'forbidden', self::message('forbidden'));
    }
}
