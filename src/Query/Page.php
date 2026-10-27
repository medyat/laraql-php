<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query;

final readonly class Page
{
    public function __construct(
        public int $number,
        public int $size,
    ) {}

    public function offset(): int
    {
        return ($this->number - 1) * $this->size;
    }
}
