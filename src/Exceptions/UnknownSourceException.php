<?php

declare(strict_types=1);

namespace Medyat\Laraql\Exceptions;

final class UnknownSourceException extends LaraqlException
{
    public function __construct(string $source)
    {
        parent::__construct(404, 'unknown_source', self::message('unknown_source', ['source' => $source]));
    }
}
