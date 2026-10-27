<?php

declare(strict_types=1);

namespace Medyat\Laraql\Source;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Medyat\Laraql\Column\Column;

/**
 * A queryable source: an Eloquent model plus the whitelist of typed columns
 * clients may read, filter, sort and search. Application concerns (tenant
 * scopes, ability flags, photos) enter through `scope()`, `decorators()` and
 * `options()`, never through the core.
 */
abstract class Source
{
    /** @var array<string, Column>|null */
    private ?array $columnMap = null;

    /** The key clients use in `source`. */
    abstract public function name(): string;

    /** @return class-string<Model> */
    abstract public function model(): string;

    /** @return list<Column> */
    abstract public function columns(): array;

    /** @return array<string, Relation> */
    public function relations(): array
    {
        return [];
    }

    /** @return array<string, Option> */
    public function options(): array
    {
        return [];
    }

    /**
     * Narrow every query on this source (tenant, ownership). Runs in the sub-query.
     *
     * @param  Builder<Model>  $query
     */
    public function scope(Builder $query): void {}

    /**
     * Add keys to every row (ability flags, computed fields).
     *
     * @return list<Closure(Model, array<string, mixed>): array<string, mixed>>
     */
    public function decorators(): array
    {
        return [];
    }

    /** Default: the model's policy `viewAny`, or allowed when there is no policy. */
    public function authorize(?Authenticatable $user): bool
    {
        $model = $this->model();

        if (Gate::getPolicyFor($model) === null) {
            return true;
        }

        return Gate::forUser($user)->allows('viewAny', $model);
    }

    public function newModel(): Model
    {
        $class = $this->model();

        return new $class;
    }

    public function key(): string
    {
        return $this->newModel()->getKeyName();
    }

    public function table(): string
    {
        return $this->newModel()->getTable();
    }

    /**
     * Columns by id; the key column is added when not declared.
     *
     * @return array<string, Column>
     */
    public function columnMap(): array
    {
        if ($this->columnMap !== null) {
            return $this->columnMap;
        }

        $map = [];

        foreach ($this->columns() as $column) {
            if (isset($map[$column->id])) {
                throw new LogicException("Source [{$this->name()}] declares column [{$column->id}] twice.");
            }

            $map[$column->id] = $column;
        }

        $key = $this->key();

        if (! isset($map[$key])) {
            $keyColumn = $this->newModel()->getKeyType() === 'int' ? Column::int($key) : Column::id($key);
            $map = [$key => $keyColumn] + $map;
        }

        return $this->columnMap = $map;
    }

    public function column(string $id): ?Column
    {
        return $this->columnMap()[$id] ?? null;
    }

    public function keyColumn(): Column
    {
        return $this->columnMap()[$this->key()];
    }

    /** @return list<Column> */
    public function searchableColumns(): array
    {
        return array_values(array_filter($this->columnMap(), fn (Column $column): bool => $column->searchable));
    }
}
