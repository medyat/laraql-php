<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query;

use Medyat\Laraql\Column\Column;

final readonly class Sort
{
    /** @param  'asc'|'desc'  $dir */
    public function __construct(
        public Column $column,
        public string $dir,
    ) {}
}
