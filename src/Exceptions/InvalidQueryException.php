<?php

declare(strict_types=1);

namespace Medyat\Laraql\Exceptions;

final class InvalidQueryException extends LaraqlException
{
    /** @param  array<string, list<string>>  $details */
    public function __construct(array $details)
    {
        parent::__construct(422, 'invalid_query', self::message('invalid_query'), $details);
    }
}
