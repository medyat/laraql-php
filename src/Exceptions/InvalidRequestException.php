<?php

declare(strict_types=1);

namespace Medyat\Laraql\Exceptions;

/** The envelope itself is unusable: answered with HTTP 422 and no results. */
final class InvalidRequestException extends LaraqlException
{
    /** @param  array<string, list<string>>  $details */
    public function __construct(array $details)
    {
        parent::__construct(422, 'invalid_request', self::message('invalid_request'), $details);
    }
}
