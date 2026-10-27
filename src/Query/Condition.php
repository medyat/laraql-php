<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query;

use Medyat\Laraql\Column\Column;
use Medyat\Laraql\Filter\Op;

/** A validated filter: the column, the operator and a value already checked for the operator's shape. */
final readonly class Condition
{
    public function __construct(
        public Column $column,
        public Op $op,
        public mixed $value = null,
    ) {}
}
