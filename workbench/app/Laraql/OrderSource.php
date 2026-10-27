<?php

declare(strict_types=1);

namespace Workbench\App\Laraql;

use Illuminate\Database\Eloquent\Builder;
use Medyat\Laraql\Column\Column;
use Medyat\Laraql\Source\Option;
use Medyat\Laraql\Source\Relation;
use Medyat\Laraql\Source\Source;
use Workbench\App\Models\Order;

/** Mirrors fixtures/v1/dataset.json · sources.order */
final class OrderSource extends Source
{
    public function name(): string
    {
        return 'order';
    }

    public function model(): string
    {
        return Order::class;
    }

    public function columns(): array
    {
        return [
            Column::int('id'),
            Column::id('code')->searchable(),
            Column::string('customer')->searchable(),
            Column::select('status', ['draft', 'released', 'done']),
            Column::money('gross_total', 'TRY'),
            Column::date('due_date'),
            Column::datetime('created_at'),
            Column::text('note'),
            Column::tags('tags'),
            Column::int('lines_count')
                ->expression('(select count(*) from order_lines where order_lines.order_id = orders.id)')
                ->notFilterable(),
        ];
    }

    public function relations(): array
    {
        return ['lines' => Relation::hasMany(OrderLineSource::class, 'order_id')];
    }

    public function options(): array
    {
        return ['only_done' => Option::scope(fn (Builder $query) => $query->where('status', 'done'))];
    }
}
