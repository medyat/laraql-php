<?php

declare(strict_types=1);

namespace Medyat\Laraql\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Expression as QueryExpression;

final class Sql
{
    /**
     * Raw SQL assembled from grammar-wrapped identifiers and constant
     * fragments; user values are always bound, never concatenated.
     */
    public static function raw(string $sql): Expression
    {
        return new QueryExpression($sql); // @phpstan-ignore argument.type (identifiers are grammar-wrapped, values are bound)
    }
}
