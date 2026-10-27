<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Medyat\Laraql\Glossary\Glossary;

it('renders one page per source with its columns, relations and options', function (): void {
    $pages = app(Glossary::class)->pages();

    expect(array_keys($pages))->toBe(['protocol.md', 'order.md', 'order_line.md']);

    $order = $pages['order.md'];
    expect($order)->toContain('# Source `order`')
        ->toContain('Sales orders.')
        ->toContain('| `code` | `id` | yes | yes | yes | Order number (SO-…), unique. |')
        ->toContain('| `status` | `select` | yes | yes | – | one of `draft`, `released`, `done`; Lifecycle state. |')
        ->toContain('| `gross_total` | `money` (TRY) | yes | yes | – | Total including VAT. |')
        ->toContain('| `lines_count` | `int`, computed | – | yes | – | Number of lines on the order. |')
        ->toContain('| `lines` | `order_line` | Products on the order, in position order. |')
        ->toContain('| `only_done` | narrows the rows | Only orders whose status is done. |')
        ->toContain('"search": "hc 80"');

    expect($pages['order_line.md'])->toContain('| `order.code` | `id` | yes | yes | yes |');
});

it('renders the protocol page from the operator table and the limits', function (): void {
    $protocol = app(Glossary::class)->protocol();

    expect($protocol)->toContain('| `date` | `eq` `before` `after` `within` `is_empty` `is_not_empty` |')
        ->toContain('| `select` | `eq` `neq` `in` `not_in` `is_empty` `is_not_empty` |')
        ->toContain('at most 20.')
        ->toContain('"size": ≤ 500')
        ->toContain('at most 10 words');
});

it('writes the pages and detects drift with --check', function (): void {
    $dir = sys_get_temp_dir().'/laraql-glossary-'.uniqid();

    $this->artisan('laraql:glossary', ['--path' => $dir])->assertSuccessful();
    expect(File::exists("{$dir}/order.md"))->toBeTrue();

    $this->artisan('laraql:glossary', ['--path' => $dir, '--check' => true])->assertSuccessful();

    File::append("{$dir}/order.md", "\nedited by hand\n");
    $this->artisan('laraql:glossary', ['--path' => $dir, '--check' => true])->assertFailed();

    File::deleteDirectory($dir);
});
