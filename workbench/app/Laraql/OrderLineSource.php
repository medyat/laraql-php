<?php

declare(strict_types=1);

namespace Workbench\App\Laraql;

use Medyat\Laraql\Column\Column;
use Medyat\Laraql\Source\Source;
use Workbench\App\Models\OrderLine;

/** Mirrors fixtures/v1/dataset.json · sources.order_line */
final class OrderLineSource extends Source
{
    public function name(): string
    {
        return 'order_line';
    }

    public function model(): string
    {
        return OrderLine::class;
    }

    public function columns(): array
    {
        return [
            Column::int('id'),
            Column::int('order_id'),
            Column::id('product_code')->searchable(),
            Column::int('qty'),
            Column::int('position'),
        ];
    }
}
