<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DatabaseSeeder;

/**
 * The two-layer promise: real columns (base table or joined) are filtered and
 * searched inside the sub-query so indexes apply; only expression columns are
 * filtered on the outer query by alias. Checked on the SQL actually sent.
 */

/** The last query sent, without identifier quotes: grammars differ (" on SQLite/PostgreSQL, ` on MySQL). */
function lastSql(): string
{
    return str_replace(['"', '`'], '', (string) collect(DB::getQueryLog())->pluck('query')->last());
}

it('joins and filters joined columns inside the sub-query', function (): void {
    DB::enableQueryLog();

    $this->actingAs(new User(['name' => 'admin']))->postJson('/laraql', ['queries' => ['q' => [
        'source' => 'order_line',
        'fields' => ['product_code', 'order.code'],
        'filters' => [['column' => 'order.code', 'op' => 'contains', 'value' => '100']],
        'search' => 'hc',
        'sort' => [['column' => 'order.code', 'dir' => 'desc']],
    ]]])->assertOk();

    $sql = lastSql();

    expect($sql)->toContain('left join orders on orders.id = order_lines.order_id');
    expect($sql)->toContain('orders.code as order__code');
    expect($sql)->toMatch('/from \(select .*LOWER\(orders\.code\) LIKE .*\) as order_lines/s');
    expect($sql)->toMatch('/order by order__code desc, id asc/');
});

it('filters expression columns on the outer query by alias', function (): void {
    DB::enableQueryLog();

    $this->actingAs(new User(['name' => 'admin']))->postJson('/laraql', ['queries' => ['q' => [
        'source' => 'order',
        'fields' => ['code', 'lines_count'],
        'sort' => [['column' => 'lines_count', 'dir' => 'desc']],
    ]]])->assertOk();

    $sql = lastSql();

    expect($sql)->toContain('((select count(*) from order_lines where order_lines.order_id = orders.id)) as lines_count'); // expressions are always parenthesised
    expect($sql)->toMatch('/\) as orders order by lines_count desc, id asc/');
});

it('runs one extra query for a load option, not one per row', function (): void {
    $this->seed(DatabaseSeeder::class);
    DB::enableQueryLog();

    $this->actingAs(new User(['name' => 'admin']))->postJson('/laraql', ['queries' => ['q' => [
        'source' => 'order',
        'fields' => ['code'],
        'options' => ['with_lines', 'product_codes'],
    ]]])->assertOk()->assertJsonCount(6, 'results.q.rows');

    expect(DB::getQueryLog())->toHaveCount(2); // rows + eager-loaded lines
});

it('ignores the model\'s own $with: eager loads come only from load options', function (): void {
    $this->seed(DatabaseSeeder::class);
    DB::enableQueryLog();

    $this->actingAs(new User(['name' => 'admin']))->postJson('/laraql', ['queries' => ['q' => [
        'source' => 'order',
        'fields' => ['code'],
    ]]])->assertOk()->assertJsonCount(6, 'results.q.rows');

    expect(DB::getQueryLog())->toHaveCount(1); // Order::$with = ['lines'] must not add a query
});

it('applies alwaysJoin() joins before any column asks for them, and never twice', function (): void {
    $admin = new User(['name' => 'admin']);

    DB::enableQueryLog();
    $this->actingAs($admin)->postJson('/laraql', ['queries' => ['q' => ['source' => 'order_line', 'fields' => ['product_code']]]])->assertOk();
    expect(substr_count(lastSql(), 'left join orders'))->toBe(1); // no order.* column, join still there for scope()

    DB::flushQueryLog();
    $this->actingAs($admin)->postJson('/laraql', ['queries' => ['q' => ['source' => 'order_line', 'fields' => ['product_code', 'order.code']]]])->assertOk();
    expect(substr_count(lastSql(), 'left join orders'))->toBe(1); // the column's join and the always join are the same key
});
