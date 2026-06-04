<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence;

use BadMethodCallException;
use Closure;
use Middag\Framework\Persistence\Query\Page;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Persistence\Relation\Relation;

/**
 * Model-aware query wrapper (the Eloquent\Builder analog).
 *
 * Wraps an immutable {@see QueryBuilder} and a prototype model: fluent methods
 * rebind the underlying builder and return $this, while the terminals hydrate
 * raw rows into model instances. This is what makes Widget::where(...)->get()
 * return Widget[] instead of plain arrays.
 *
 * @template TModel of Model
 *
 * @api
 */
class ModelQuery
{
    /** @var list<string> */
    private array $eagerLoad = [];

    /**
     * @param TModel $model
     */
    public function __construct(
        private QueryBuilder $query,
        private readonly Model $model,
    ) {}

    /**
     * Dispatch a local scope: $query->active() invokes Model::scopeActive($query).
     * The scope mutates this query (its fluent methods rebind the builder) and we
     * return $this so calls keep chaining.
     *
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): static
    {
        $scope = 'scope' . ucfirst($method);

        if (method_exists($this->model, $scope)) {
            $this->model->{$scope}($this, ...$parameters); // @phpstan-ignore-line — dynamic local-scope dispatch on the model

            return $this;
        }

        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s() (no local scope "%s").',
            $this->model::class,
            $method,
            $scope,
        ));
    }

    // ========================================================================
    // FLUENT PASSTHROUGH (rebinds the immutable builder, returns $this)
    // ========================================================================

    public function where(Closure|string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        $this->query = match (func_num_args()) {
            1 => $this->query->where($column),
            2 => $this->query->where($column, $operator),
            3 => $this->query->where($column, $operator, $value),
            default => $this->query->where($column, $operator, $value, $boolean),
        };

        return $this;
    }

    public function orWhere(Closure|string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query = match (func_num_args()) {
            1 => $this->query->orWhere($column),
            2 => $this->query->orWhere($column, $operator),
            default => $this->query->orWhere($column, $operator, $value),
        };

        return $this;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values): static
    {
        $this->query = $this->query->whereIn($column, $values);

        return $this;
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereNotIn(string $column, array $values): static
    {
        $this->query = $this->query->whereNotIn($column, $values);

        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->query = $this->query->whereBetween($column, $min, $max);

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->query = $this->query->whereNull($column);

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->query = $this->query->whereNotNull($column);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query = $this->query->orderBy($column, $direction);

        return $this;
    }

    public function latest(string $column = 'id'): static
    {
        $this->query = $this->query->latest($column);

        return $this;
    }

    public function oldest(string $column = 'id'): static
    {
        $this->query = $this->query->oldest($column);

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->query = $this->query->limit($limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->query = $this->query->offset($offset);

        return $this;
    }

    public function forPage(int $page, int $perPage): static
    {
        $this->query = $this->query->forPage($page, $perPage);

        return $this;
    }

    public function select(string ...$columns): static
    {
        $this->query = $this->query->select(...$columns);

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): static
    {
        $this->query = $this->query->join($table, $first, $operator, $second);

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->query = $this->query->leftJoin($table, $first, $operator, $second);

        return $this;
    }

    public function whereColumn(string $first, ?string $operator = null, ?string $second = null): static
    {
        $this->query = match (func_num_args()) {
            2 => $this->query->whereColumn($first, $operator),
            default => $this->query->whereColumn($first, $operator, $second),
        };

        return $this;
    }

    public function orWhereColumn(string $first, ?string $operator = null, ?string $second = null): static
    {
        $this->query = match (func_num_args()) {
            2 => $this->query->orWhereColumn($first, $operator),
            default => $this->query->orWhereColumn($first, $operator, $second),
        };

        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->query = $this->query->groupBy(...$columns);

        return $this;
    }

    public function having(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query = match (func_num_args()) {
            2 => $this->query->having($column, $operator),
            default => $this->query->having($column, $operator, $value),
        };

        return $this;
    }

    public function orHaving(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query = match (func_num_args()) {
            2 => $this->query->orHaving($column, $operator),
            default => $this->query->orHaving($column, $operator, $value),
        };

        return $this;
    }

    public function orderByDesc(string $column): static
    {
        $this->query = $this->query->orderByDesc($column);

        return $this;
    }

    // ========================================================================
    // HYDRATING TERMINALS
    // ========================================================================

    /**
     * @return array<int, TModel>
     */
    public function get(): array
    {
        $models = array_map(
            fn (array $row): Model => $this->model->newFromBuilder($row),
            $this->query->get(),
        );

        if ($models !== [] && $this->eagerLoad !== []) {
            return $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /**
     * @return null|TModel
     */
    public function first(): ?Model
    {
        $row = $this->query->first();

        if ($row === null) {
            return null;
        }

        $model = $this->model->newFromBuilder($row);

        if ($this->eagerLoad !== []) {
            $this->eagerLoadRelations([$model]);
        }

        return $model;
    }

    /**
     * @return null|TModel
     */
    public function find(mixed $id): ?Model
    {
        $row = $this->query->find($id, $this->model->getKeyName());

        return $row === null ? null : $this->model->newFromBuilder($row);
    }

    /**
     * @return Page<TModel>
     */
    public function paginate(int $page, int $perPage): Page
    {
        $result = $this->query->paginate($page, $perPage);

        $items = array_map(
            fn (array $row): Model => $this->model->newFromBuilder($row),
            $result->items(),
        );

        return new Page($items, $result->total(), $result->page(), $result->perpage());
    }

    public function count(): int
    {
        return $this->query->count();
    }

    public function sum(string $column): float|int
    {
        return $this->query->sum($column);
    }

    public function avg(string $column): float|int|null
    {
        return $this->query->avg($column);
    }

    public function min(string $column): mixed
    {
        return $this->query->min($column);
    }

    public function max(string $column): mixed
    {
        return $this->query->max($column);
    }

    /**
     * @return iterable<int, TModel>
     */
    public function cursor(): iterable
    {
        foreach ($this->query->cursor() as $row) {
            yield $this->model->newFromBuilder($row);
        }
    }

    /**
     * @return iterable<int, TModel>
     */
    public function lazy(int $chunkSize = 1000): iterable
    {
        foreach ($this->query->lazy($chunkSize) as $row) {
            yield $this->model->newFromBuilder($row);
        }
    }

    /**
     * @param callable(array<int, TModel>): mixed $callback
     */
    public function chunk(int $size, callable $callback): bool
    {
        return $this->query->chunk($size, function (array $rows) use ($callback) {
            $models = array_map(
                fn (array $row): Model => $this->model->newFromBuilder($row),
                $rows,
            );

            return $callback($models);
        });
    }

    public function exists(): bool
    {
        return $this->query->exists();
    }

    public function value(string $column): mixed
    {
        return $this->query->value($column);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        return $this->query->pluck($column, $key);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        return $this->query->update($values);
    }

    public function delete(): int
    {
        return $this->query->delete();
    }

    public function toSql(): string
    {
        return $this->query->toSql();
    }

    public function toBuilder(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * @return TModel
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Queue relations to eager-load with the result set (avoids N+1 reads).
     */
    public function with(string ...$relations): static
    {
        $this->eagerLoad = array_values(array_merge($this->eagerLoad, $relations));

        return $this;
    }

    /**
     * Load every queued relation onto the given models — one query per relation.
     *
     * @param array<int, Model> $models
     *
     * @return array<int, Model>
     */
    public function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name) {
            $relation = $this->model->{$name}(); // @phpstan-ignore-line — relationship method on the model

            if ($relation instanceof Relation) {
                $relation->addEagerConstraints($models);
                $models = $relation->match($models, $relation->getEager(), $name);
            }
        }

        return $models;
    }
}
