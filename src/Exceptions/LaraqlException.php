<?php

declare(strict_types=1);

namespace Medyat\Laraql\Exceptions;

use RuntimeException;

/**
 * A per-query failure, rendered as `{ error: { status, code, message, details? } }`
 * inside the results envelope (docs/protocol.md · Errors).
 */
class LaraqlException extends RuntimeException
{
    /** @param  array<string, list<string>>  $details */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /** @return array{status: int, code: string, message: string, details?: array<string, list<string>>} */
    public function toArray(): array
    {
        $error = ['status' => $this->status, 'code' => $this->errorCode, 'message' => $this->getMessage()];

        if ($this->details !== []) {
            $error['details'] = $this->details;
        }

        return $error;
    }

    /** @param  array<string, scalar>  $params */
    public static function message(string $key, array $params = []): string
    {
        $text = __('laraql::messages.'.$key, $params);

        return is_string($text) ? $text : $key;
    }
}
