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

    public function describe(): ?string
    {
        return 'Sales orders. Query here for order headers; use the lines relation or the order_line source for products on an order.';
    }

    public function columns(): array
    {
        return [
            Column::int('id'),
            Column::id('code')->searchable()->describe('Order number (SO-…), unique.'),
            Column::string('customer')->searchable()->describe('Customer name as printed on the order.'),
            Column::select('status', ['draft', 'released', 'done'])->describe('Lifecycle state.'),
            Column::money('gross_total', 'TRY')->describe('Total including VAT.'),
            Column::date('due_date')->describe('Promised delivery date; empty when not agreed yet.'),
            Column::datetime('created_at'),
            Column::text('note'),
            Column::tags('tags')->describe('Free labels such as export, vip, rush.'),
            Column::int('lines_count')
                ->expression('(select count(*) from order_lines where order_lines.order_id = orders.id)')
                ->notFilterable()
                ->describe('Number of lines on the order.'),
        ];
    }

    public function relations(): array
    {
        return ['lines' => Relation::hasMany(OrderLineSource::class, 'order_id', description: 'Products on the order, in position order.')];
    }

    public function options(): array
    {
        return ['only_done' => Option::scope(fn (Builder $query) => $query->where('status', 'done'), 'Only orders whose status is done.')];
    }
}
