<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Medyat\Laraql\Column\Column;
use Medyat\Laraql\Context;
use Medyat\Laraql\Filter\Op;
use Medyat\Laraql\Query\Compiler\QueryCompiler;
use Medyat\Laraql\Result\RowFormatter;
use Medyat\Laraql\Source\Option;
use Medyat\Laraql\Source\Source;
use Medyat\Laraql\Support\Raw;

/**
 * Runs a validated query: rows (with `hasMore` from one extra row and the
 * includes attached per parent) or a count.
 */
final readonly class Executor
{
    public function __construct(
        private QueryCompiler $compiler,
        private RowFormatter $formatter,
    ) {}

    /** @return array{rows: list<array<string, mixed>>, page: array{number: int, size: int, hasMore: bool}} */
    public function rows(Query $query, Context $context): array
    {
        $output = $this->output($query->source, $query->fields);
        $select = $this->select($query->source, $output, $query->filters, $query->sort, array_map(
            fn (IncludeQuery $include): Column => $this->localKey($query->source, $include),
            array_values($query->include),
        ));

        $models = $this->compiler
            ->build($query->source, $select, $query->filters, $query->sort, $query->searchTokens, $query->options, $context)
            ->offset($query->page->offset())
            ->limit($query->page->size + 1)
            ->get();

        $hasMore = $models->count() > $query->page->size;
        $models = $models->take($query->page->size);

        $rows = $this->formatAll($models, $output, $query->source, $query->options);
        $this->attachIncludes($rows, $models, $query->source, $query->include, $context);

        return [
            'rows' => $rows,
            'page' => ['number' => $query->page->number, 'size' => $query->page->size, 'hasMore' => $hasMore],
        ];
    }

    public function count(Query $query, Context $context): int
    {
        $select = $this->select($query->source, [$query->source->keyColumn()], $query->filters, [], []);

        return $this->compiler
            ->build($query->source, $select, $query->filters, [], $query->searchTokens, $query->options, $context)
            ->count();
    }

    /**
     * Output columns: the key first, then the requested fields (or every column).
     *
     * @param  list<Column>|null  $fields
     * @return list<Column>
     */
    private function output(Source $source, ?array $fields): array
    {
        $columns = [$source->keyColumn()];

        foreach ($fields ?? array_values($source->columnMap()) as $column) {
            if ($column->id !== $source->key()) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Columns the sub-query must expose: output + filtered + sorted + relation keys, each once.
     *
     * @param  list<Column>  $output
     * @param  list<Condition>  $filters
     * @param  list<Sort>  $sort
     * @param  list<Column>  $extra
     * @return list<Column>
     */
    private function select(Source $source, array $output, array $filters, array $sort, array $extra): array
    {
        $byId = [];

        foreach ([...$output, ...array_map(fn (Condition $c): Column => $c->column, $filters), ...array_map(fn (Sort $s): Column => $s->column, $sort), ...$extra] as $column) {
            $byId[$column->id] ??= $column;
        }

        return array_values($byId);
    }

    /**
     * @param  Collection<int, Model>  $models
     * @param  list<Column>  $output
     * @param  list<string>  $options
     * @return list<array<string, mixed>>
     */
    private function formatAll(Collection $models, array $output, Source $source, array $options): array
    {
        $decorators = $source->decorators();

        foreach ($options as $name) {
            $option = $source->options()[$name];

            if ($option->kind === Option::DECORATE && $option->decorator !== null) {
                $decorators[] = $option->decorator;
            }
        }

        return array_values($models->map(fn (Model $model): array => $this->formatter->format($model, $output, $decorators))->all());
    }

    /**
     * Loads every include with one query per relation and nests the related
     * rows under the relation key, `limit` per parent.
     *
     * shortcut: the per-parent limit is applied in PHP after fetching every
     * related row of the page; switch to a window function (ROW_NUMBER OVER
     * PARTITION BY) when parents × related rows grows large.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<int, Model>  $models
     * @param  array<string, IncludeQuery>  $includes
     */
    private function attachIncludes(array &$rows, Collection $models, Source $source, array $includes, Context $context): void
    {
        foreach ($includes as $include) {
            $localKey = $this->localKey($source, $include);
            $foreignKey = $include->source->column($include->relation->foreignKey)
                ?? throw new LogicException("Relation [{$include->name}] of source [{$source->name()}] points at undeclared column [{$include->relation->foreignKey}].");

            $parentKeys = $models->map(fn (Model $model): mixed => $model->getAttribute($localKey->alias()))->filter()->unique()->values()->all();
            $output = $this->output($include->source, $include->fields);
            $filters = [...$include->filters, new Condition($foreignKey, Op::In, $parentKeys)];
            $select = $this->select($include->source, $output, $filters, $include->sort, [
                $foreignKey,
                ...array_map(fn (IncludeQuery $nested): Column => $this->localKey($include->source, $nested), array_values($include->include)),
            ]);

            $related = $parentKeys === []
                ? new Collection
                : $this->compiler->build($include->source, $select, $filters, $include->sort, [], [], $context)->get();

            $grouped = $related->groupBy(fn (Model $model): string => Raw::string($model->getAttribute($foreignKey->alias())));

            foreach ($models->values() as $i => $model) {
                $parent = Raw::string($model->getAttribute($localKey->alias()));
                /** @var Collection<int, Model> $children */
                $children = ($grouped[$parent] ?? new Collection)->take($include->limit)->values();
                $childRows = $this->formatAll($children, $output, $include->source, []);
                $this->attachIncludes($childRows, $children, $include->source, $include->include, $context);
                $rows[$i][$include->name] = $childRows;
            }
        }
    }

    private function localKey(Source $source, IncludeQuery $include): Column
    {
        $id = $include->relation->localKey ?? $source->key();

        return $source->column($id)
            ?? throw new LogicException("Relation [{$include->name}] of source [{$source->name()}] uses undeclared local key [{$id}].");
    }
}
