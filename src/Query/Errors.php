<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query;

use Medyat\Laraql\Exceptions\InvalidQueryException;
use Medyat\Laraql\Exceptions\LaraqlException;

/** Collects validation messages by path so one answer reports every problem. */
final class Errors
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @param  array<string, scalar>  $params */
    public function add(string $path, string $key, array $params = []): void
    {
        $this->errors[$path][] = LaraqlException::message($key, $params);
    }

    public function any(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, list<string>> */
    public function all(): array
    {
        return $this->errors;
    }

    public function throwIfAny(): void
    {
        if ($this->any()) {
            throw new InvalidQueryException($this->errors);
        }
    }
}
