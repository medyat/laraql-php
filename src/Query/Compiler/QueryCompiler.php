<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query\Compiler;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Medyat\Laraql\Column\Column;
use Medyat\Laraql\Context;
use Medyat\Laraql\Query\Condition;
use Medyat\Laraql\Query\Sort;
use Medyat\Laraql\Source\Option;
use Medyat\Laraql\Source\Source;
use Medyat\Laraql\Support\Sql;

/**
 * Builds the two-layer query: the sub-query selects the whitelisted columns
 * under their aliases (plain columns, joined columns, expressions) and takes
 * the filters that can be pushed down; the outer query runs over the
 * sub-query's aliases for everything else, then sorts and pages.
 */
final readonly class QueryCompiler
{
    public function __construct(private FilterCompiler $filters) {}

    /**
     * @param  list<Column>  $select  Columns the sub-query must expose (output + filter/sort/relation keys).
     * @param  list<Condition>  $conditions
     * @param  list<Sort>  $sort
     * @param  list<string>  $searchTokens
     * @param  list<string>  $options
     * @return Builder<Model>
     */
    public function build(Source $source, array $select, array $conditions, array $sort, array $searchTokens, array $options, Context $context): Builder
    {
        $table = $source->table();
        $inner = $source->newModel()->newQuery();
        $source->scope($inner);

        foreach ($options as $name) {
            $option = $source->options()[$name];

            if ($option->kind === Option::SCOPE && $option->scope !== null) {
                ($option->scope)($inner);
            }
        }

        $inner->select(array_map(fn (Column $column) => $this->selectExpression($column, $table), $select));

        $dates = new DateRange($context);
        $deferred = [];

        foreach ($conditions as $condition) {
            $condition->column->isPlain()
                ? $this->filters->apply($inner, "{$table}.{$condition->column->from}", $condition, $dates)
                : $deferred[] = $condition;
        }

        $searchable = $source->searchableColumns();
        $searchPushable = $searchTokens !== [] && array_all($searchable, fn (Column $column): bool => $column->isPlain());

        if ($searchPushable) {
            $this->filters->search($inner, array_map(fn (Column $column): string => "{$table}.{$column->from}", $searchable), $searchTokens);
        }

        $outer = $source->newModel()->newQueryWithoutScopes()->fromSub($inner, $table);

        foreach ($deferred as $condition) {
            $this->filters->apply($outer, $condition->column->alias(), $condition, $dates);
        }

        if ($searchTokens !== [] && ! $searchPushable) {
            $this->filters->search($outer, array_map(fn (Column $column): string => $column->alias(), $searchable), $searchTokens);
        }

        foreach ($options as $name) {
            $option = $source->options()[$name];

            if ($option->kind === Option::LOAD && $option->relation !== null) {
                $outer->with($option->relation);
            }
        }

        $this->orderBy($outer, $sort, $source->keyColumn());

        return $outer;
    }

    /**
     * Deterministic order: the requested keys, then the primary key ascending.
     *
     * @param  Builder<Model>  $query
     * @param  list<Sort>  $sort
     */
    private function orderBy(Builder $query, array $sort, Column $key): void
    {
        $keyed = false;

        foreach ($sort as $item) {
            $query->orderBy($item->column->alias(), $item->dir);
            $keyed = $keyed || $item->column->id === $key->id;
        }

        if (! $keyed) {
            $query->orderBy($key->alias(), 'asc');
        }
    }

    private function selectExpression(Column $column, string $table): string|Expression
    {
        $alias = $column->alias();

        if ($column->expression !== null) {
            $grammar = DB::connection()->getQueryGrammar();

            return Sql::raw('('.$column->expression.') as '.$grammar->wrap($alias));
        }

        $from = str_contains($column->from, '.') ? $column->from : "{$table}.{$column->from}";

        return "{$from} as {$alias}";
    }
}
