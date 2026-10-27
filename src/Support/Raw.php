<?php

declare(strict_types=1);

namespace Medyat\Laraql\Support;

/** Narrowing helpers for untyped request data. */
final class Raw
{
    /**
     * String-keyed view of a value; anything else is an empty array.
     *
     * @return array<string, mixed>
     */
    public static function assoc(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $assoc = [];

        foreach ($value as $key => $item) {
            $assoc[(string) $key] = $item;
        }

        return $assoc;
    }

    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
