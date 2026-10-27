<?php

declare(strict_types=1);

namespace Medyat\Laraql\Filter;

/**
 * The 18 filter operators of the protocol. Semantics depend on the column
 * type (docs/protocol.md · Filter).
 */
enum Op: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
    case Between = 'between';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case In = 'in';
    case NotIn = 'not_in';
    case Before = 'before';
    case After = 'after';
    case Within = 'within';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    public function expectsValue(): bool
    {
        return ! in_array($this, [self::IsEmpty, self::IsNotEmpty], true);
    }
}
