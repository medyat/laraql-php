<?php

declare(strict_types=1);

namespace Medyat\Laraql\Glossary;

use Medyat\Laraql\Column\Column;
use Medyat\Laraql\Column\Type;
use Medyat\Laraql\Filter\Op;
use Medyat\Laraql\Source\Option;
use Medyat\Laraql\Source\Registry;
use Medyat\Laraql\Source\Source;
use Medyat\Laraql\Support\Raw;

/**
 * Renders the markdown an LLM reads before querying: one protocol page and
 * one page per source, all derived from the source definitions so they
 * cannot drift from what the validator accepts.
 */
final readonly class Glossary
{
    /** @param  array<string, mixed>  $limits  The `laraql.limits` config. */
    public function __construct(
        private Registry $registry,
        private array $limits,
    ) {}

    /**
     * File name → markdown for every page.
     *
     * @return array<string, string>
     */
    public function pages(): array
    {
        $pages = ['protocol.md' => $this->protocol()];

        foreach ($this->registry->names() as $name) {
            $pages["{$name}.md"] = $this->source($this->registry->get($name));
        }

        return $pages;
    }

    public function protocol(): string
    {
        $lines = [
            '# Laraql query protocol',
            '',
            'Read this once per conversation before calling any laraql query tool. Each source has its own page listing its columns, relations and options; never guess column names or operators.',
            '',
            '## Request',
            '',
            'A batch of queries keyed by a name of your choice; every key is answered under the same name and one failing query does not fail the others.',
            '',
            '```json',
            '{ "queries": { "orders": { "source": "order", "fields": ["code", "gross_total"], "filters": [{ "column": "status", "op": "eq", "value": "done" }], "sort": [{ "column": "due_date", "dir": "desc" }], "page": { "number": 1, "size": 50 } } } }',
            '```',
            '',
            '| Field | Meaning |',
            '|---|---|',
            '| `source` | Source key, required. |',
            '| `kind` | `rows` (default) or `count`. `count` returns only `total` and ignores `fields`, `sort`, `page`, `include`. |',
            '| `fields` | Columns to return. Request only what you need; the key column is always included. |',
            '| `filters` | Conditions combined with AND, `{ "column", "op", "value" }`; at most '.$this->limit('filters').'. |',
            '| `sort` | `[{ "column", "dir": "asc" \\| "desc" }]`, at most '.$this->limit('sort').'; the key column breaks ties. |',
            '| `page` | `{ "number": 1-based, "size": ≤ '.$this->pageSize('max').' }`, default size '.$this->pageSize('default').'. |',
            '| `search` | Free text over the searchable columns: every word must appear in at least one searchable column, order does not matter, case-insensitive; at most '.$this->limit('search_tokens').' words. |',
            '| `include` | Related rows per parent row: `{ "relation": { "fields", "filters", "sort", "limit" } }`, only relations the source page lists; `limit` default '.$this->includeLimit('limit').', max '.$this->includeLimit('max_limit').'. |',
            '| `options` | Names from the source page; each applies a server-side behaviour. |',
            '',
            '## Response',
            '',
            '```json',
            '{ "results": { "orders": { "rows": [ { "id": 1, "code": "SO-1001", "gross_total": 1250 } ], "page": { "number": 1, "size": 50, "hasMore": false } } } }',
            '```',
            '',
            '`page.hasMore` tells whether a next page exists; there is no total unless you send a `kind: "count"` query (answer: `{ "total": n }`). A failed query answers `{ "error": { "status": 404 | 403 | 422, "code", "message", "details" } }` where `details` maps the offending path (e.g. `filters.0.op`) to messages.',
            '',
            '## Operators by column type',
            '',
            '| Type | Operators | Value |',
            '|---|---|---|',
        ];

        foreach (Type::cases() as $type) {
            $lines[] = '| `'.$type->value.'` | '.implode(' ', array_map(fn (Op $op): string => '`'.$op->value.'`', $type->operators())).' | '.$this->valueHint($type).' |';
        }

        $lines = [...$lines, '',
            '- `is_empty` / `is_not_empty` take no value. On text columns empty means NULL or `""`; on list columns NULL or `[]`.',
            '- `between` takes `[min, max]`; one side may be `null` for an open range.',
            '- `in` / `not_in` take a list (≤ '.$this->limit('in_values').' values); on list columns (`multiselect`, `tags`) they mean "contains any of" / "contains none of".',
            '- `contains`, `starts_with`, `ends_with` are case-insensitive substring matches; `%` and `_` are literal.',
            '- `eq` on a date column matches the whole day; `before` / `after` are strict; `within` takes `{ "preset": "today" | "this_week" | "next_7_days" | "this_month" | "last_30_days" | "this_quarter" | "overdue" }` or `{ "from": "YYYY-MM-DD", "to": "YYYY-MM-DD" }` (inclusive). Dates resolve in the request timezone.',
            '',
            '## Rules',
            '',
            '- Use `search` for "find something like X", filters for exact conditions; combine them freely (AND).',
            '- Fetch several records by key with one `in` filter instead of several queries.',
            '- Do not page through a whole source; narrow first, then page. Use `kind: "count"` to size a result before listing it.',
            '- Unknown columns, operators, options or relations are rejected (422); read the source page instead of guessing.',
        ];

        return implode("\n", $lines)."\n";
    }

    public function source(Source $source): string
    {
        $lines = ['# Source `'.$source->name().'`', ''];

        if ($source->describe() !== null) {
            $lines[] = $source->describe();
            $lines[] = '';
        }

        $lines = [...$lines, 'Key column: `'.$source->key().'`.', '', '## Columns', '', '| Column | Type | Filter | Sort | Search | Notes |', '|---|---|---|---|---|---|'];

        foreach ($source->columnMap() as $column) {
            $lines[] = '| `'.$column->id.'` | '.$this->typeLabel($column).' | '.$this->mark($column->filterable).' | '.$this->mark($column->sortable).' | '.$this->mark($column->searchable).' | '.$this->notes($column).' |';
        }

        $relations = $source->relations();

        if ($relations !== []) {
            $lines = [...$lines, '', '## Relations (`include`)', '', '| Relation | Source | Notes |', '|---|---|---|'];

            foreach ($relations as $name => $relation) {
                $lines[] = '| `'.$name.'` | `'.$this->registry->ofClass($relation->source)->name().'` | '.($relation->description ?? '').' |';
            }
        }

        $options = $source->options();

        if ($options !== []) {
            $lines = [...$lines, '', '## Options', '', '| Option | Effect | Notes |', '|---|---|---|'];

            foreach ($options as $name => $option) {
                $lines[] = '| `'.$name.'` | '.$this->optionEffect($option).' | '.($option->description ?? '').' |';
            }
        }

        return implode("\n", [...$lines, '', '## Examples', '', ...$this->examples($source)])."\n";
    }

    private function typeLabel(Column $column): string
    {
        $label = '`'.$column->type->value.'`';

        if ($column->type === Type::Money && $column->currency !== null) {
            $label .= ' ('.$column->currency.')';
        }

        if ($column->isExpression()) {
            $label .= ', computed';
        }

        return $label;
    }

    private function notes(Column $column): string
    {
        $notes = [];

        if ($column->options !== null) {
            $notes[] = 'one of '.implode(', ', array_map(fn (string|int $o): string => '`'.$o.'`', $column->options));
        }

        if ($column->description !== null) {
            $notes[] = $column->description;
        }

        return implode('; ', $notes);
    }

    private function optionEffect(Option $option): string
    {
        return match ($option->kind) {
            Option::SCOPE => 'narrows the rows',
            Option::LOAD => 'loads `'.Raw::string($option->relation).'` onto each row',
            default => 'adds keys to each row',
        };
    }

    /** @return list<string> */
    private function examples(Source $source): array
    {
        $key = $source->key();
        $searchable = $source->searchableColumns();
        $first = $searchable[0] ?? null;
        $fields = array_values(array_filter(array_map(fn (Column $c): string => $c->id, array_slice(array_values($source->columnMap()), 0, 3)), fn (string $id): bool => $id !== $key));
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_SLASHES);

        $examples = [
            'One record by key:',
            '',
            '```json',
            '{ "source": "'.$source->name().'", "fields": '.$fieldsJson.', "filters": [{ "column": "'.$key.'", "op": "eq", "value": 1 }], "page": { "size": 1 } }',
            '```',
        ];

        if ($first !== null) {
            $examples = [...$examples, '', 'Partial text in the searchable columns ('.implode(', ', array_map(fn (Column $c): string => '`'.$c->id.'`', $searchable)).'):', '', '```json',
                '{ "source": "'.$source->name().'", "fields": '.$fieldsJson.', "search": "hc 80", "page": { "size": 20 } }', '```'];
        }

        return [...$examples, '', 'How many match:', '', '```json', '{ "source": "'.$source->name().'", "kind": "count", "filters": [{ "column": "'.$key.'", "op": "is_not_empty" }] }', '```'];
    }

    private function valueHint(Type $type): string
    {
        return match (true) {
            $type->isNumeric() => 'number',
            $type === Type::Bool => '`true` / `false`',
            $type->isDate() => '`YYYY-MM-DD`, or a preset / range for `within`',
            $type->isList() => 'list of values',
            $type === Type::Json => '—',
            default => 'string (list for `in` / `not_in`)',
        };
    }

    private function mark(bool $flag): string
    {
        return $flag ? 'yes' : '–';
    }

    private function limit(string $key): string
    {
        return Raw::string($this->limits[$key] ?? '?');
    }

    private function pageSize(string $key): string
    {
        $size = Raw::assoc($this->limits['page_size'] ?? []);

        return Raw::string($size[$key] ?? '?');
    }

    private function includeLimit(string $key): string
    {
        $include = Raw::assoc($this->limits['include'] ?? []);

        return Raw::string($include[$key] ?? '?');
    }
}
