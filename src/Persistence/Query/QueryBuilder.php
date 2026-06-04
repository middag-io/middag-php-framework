<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Persistence\Query;

use Closure;
use InvalidArgumentException;
use LogicException;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Database\Enum\Capability;
use Middag\Framework\Database\StandardSqlDialect;
use Middag\Framework\Persistence\Contract\QueryBuilderInterface;
use Middag\Framework\Shared\Enum\Operator;

/**
 * Eloquent-like fluent query builder with OFF/ON modes.
 *
 * ON mode (built WITH a connection via on() or a Model) executes terminals
 * (get/first/count/paginate/insert/update/delete) directly against the
 * {@see ConnectionAdapterInterface}. OFF mode (built WITHOUT a connection via for())
 * only constructs the query AST: compile()/toSql() return inspectable SQL +
 * positional bindings for caching, testing, or feeding another ORM, while the
 * execution terminals throw. "Eloquent to help, not hinder."
 *
 * Immutable: every fluent method returns a fresh copy, so a builder can be
 * branched and reused without aliasing surprises. The read path compiles ANSI
 * SQL with positional `?` placeholders; the per-row write helpers on the
 * {@see ConnectionAdapterInterface} own their own placeholder style.
 *
 * @api
 */
class QueryBuilder implements QueryBuilderInterface
{
    private const COMPARISON_OPERATORS = [
        Operator::EQ->value => true,
        Operator::NEQ->value => true,
        Operator::GT->value => true,
        Operator::GTE->value => true,
        Operator::LT->value => true,
        Operator::LTE->value => true,
        Operator::LIKE->value => true,
    ];

    /** @var array<int, string> */
    protected array $columns = ['*'];

    protected bool $distinct = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $wheres = [];

    /**
     * @var array<int, array{type: string, table: string, first: string, operator: string, second: string}>
     */
    protected array $joins = [];

    /**
     * @var array<int, array{column: string, direction: string}>
     */
    protected array $orders = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    /** Row-lock mode: 'update' (FOR UPDATE), 'share' (FOR SHARE), or null (no lock). */
    protected ?string $lockMode = null;

    /** @var array<int, string> */
    protected array $groups = [];

    /**
     * @var array<int, array{boolean: string, column: string, operator: string, value: mixed}>
     */
    protected array $havings = [];

    /**
     * @var array<int, array{query: QueryBuilder, all: bool}>
     */
    protected array $unions = [];

    public function __construct(
        protected string $table,
        protected ?ConnectionAdapterInterface $connection = null,
    ) {}

    /**
     * Build an OFF-mode (connection-less) builder for SQL inspection.
     */
    public static function for(string $table): static
    {
        return new static($table); // @phpstan-ignore-line — designed for subclassing (core MiddagItemQueryBuilder); subclasses keep the constructor shape
    }

    /**
     * Build an ON-mode builder bound to a connection; terminals execute.
     */
    public static function on(ConnectionAdapterInterface $connection, string $table): static
    {
        return new static($table, $connection); // @phpstan-ignore-line — designed for subclassing (core MiddagItemQueryBuilder); subclasses keep the constructor shape
    }

    // ========================================================================
    // WHERE
    // ========================================================================

    /**
     * Add a WHERE condition.
     *
     * Forms: where(Closure) for a nested AND group; where(col, value) for an
     * equality shortcut; where(col, Operator|opString, value) for an explicit
     * comparison. Because the builder is immutable, a group Closure receives a
     * fresh builder and MUST return it, e.g.
     * where(fn (QueryBuilder $q) => $q->where('a', 1)->orWhere('b', 2)).
     */
    public function where(Closure|string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (func_num_args() < 3) {
            if (func_num_args() < 2) {
                throw new InvalidArgumentException('where() requires a value or a comparison operator and value.');
            }
            $value = $operator;
            $resolved = Operator::EQ;
        } else {
            $resolved = $this->resolveComparisonOperator($operator);
        }

        return $this->pushWhere([
            'type' => 'basic',
            'boolean' => $this->normalizeBoolean($boolean),
            'column' => $column,
            'operator' => $resolved,
            'value' => $value,
        ]);
    }

    public function orWhere(Closure|string $column, mixed $operator = null, mixed $value = null): static
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, 'or');
        }

        if (func_num_args() < 2) {
            throw new InvalidArgumentException('orWhere() requires a value or a comparison operator and value.');
        }

        if (func_num_args() < 3) {
            // 2-arg shortcut: orWhere(col, value) -> equality. Pass an explicit
            // operator so where() sees a 4-arg call and treats $operator as the value.
            return $this->where($column, Operator::EQ, $operator, 'or');
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        return $this->pushWhere([
            'type' => 'in',
            'boolean' => $this->normalizeBoolean($boolean),
            'column' => $column,
            'values' => array_values($values),
            'not' => $not,
        ]);
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * @param array<int, mixed> $values
     */
    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'and', bool $not = false): static
    {
        return $this->pushWhere([
            'type' => 'between',
            'boolean' => $this->normalizeBoolean($boolean),
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'not' => $not,
        ]);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        return $this->pushWhere([
            'type' => 'null',
            'boolean' => $this->normalizeBoolean($boolean),
            'column' => $column,
            'not' => $not,
        ]);
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a WHERE that compares two columns (no bound value), e.g.
     * whereColumn('updated_at', '>', 'created_at'). The 2-arg form
     * whereColumn('a', 'b') is an equality shortcut.
     */
    public function whereColumn(string $first, ?string $operator = null, ?string $second = null, string $boolean = 'and'): static
    {
        if (func_num_args() < 3) {
            if ($operator === null) {
                throw new InvalidArgumentException('whereColumn() requires a second column.');
            }
            $second = $operator;
            $sqlOperator = Operator::EQ->value;
        } else {
            $sqlOperator = $this->resolveComparisonOperator($operator)->value;
        }

        if ($second === null) {
            throw new InvalidArgumentException('whereColumn() requires a second column.');
        }

        return $this->pushWhere([
            'type' => 'column',
            'boolean' => $this->normalizeBoolean($boolean),
            'first' => $first,
            'operator' => $sqlOperator,
            'second' => $second,
        ]);
    }

    public function orWhereColumn(string $first, ?string $operator = null, ?string $second = null): static
    {
        if (func_num_args() < 3) {
            // 2-arg shortcut: orWhereColumn(first, second) -> equality. Pass an
            // explicit operator so whereColumn() sees a 4-arg call.
            return $this->whereColumn($first, '=', $operator, 'or');
        }

        return $this->whereColumn($first, $operator, $second, 'or');
    }

    // ========================================================================
    // SELECT / DISTINCT / JOIN / ORDER / LIMIT
    // ========================================================================

    public function select(string ...$columns): static
    {
        return $this->copy(function (self $q) use ($columns): void {
            $q->columns = $columns === [] ? ['*'] : array_values($columns);
        });
    }

    public function addSelect(string ...$columns): static
    {
        return $this->copy(function (self $q) use ($columns): void {
            $base = $q->columns === ['*'] ? [] : $q->columns;
            $q->columns = array_values(array_merge($base, $columns));
        });
    }

    public function distinct(bool $value = true): static
    {
        return $this->copy(function (self $q) use ($value): void {
            $q->distinct = $value;
        });
    }

    public function join(string $table, string $first, string $operator, string $second): static
    {
        return $this->addJoin('inner', $table, $first, $operator, $second);
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->addJoin('left', $table, $first, $operator, $second);
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $normalized = strtolower($direction);
        if ($normalized !== 'asc' && $normalized !== 'desc') {
            throw new InvalidArgumentException(sprintf('Order direction must be "asc" or "desc", got "%s".', $direction));
        }

        return $this->copy(function (self $q) use ($column, $normalized): void {
            $q->orders[] = ['column' => $column, 'direction' => $normalized];
        });
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string $column = 'id'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'id'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $limit): static
    {
        return $this->copy(function (self $q) use ($limit): void {
            $q->limit = max(0, $limit);
        });
    }

    public function offset(int $offset): static
    {
        return $this->copy(function (self $q) use ($offset): void {
            $q->offset = max(0, $offset);
        });
    }

    public function forPage(int $page, int $perPage): static
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    /**
     * Acquire an exclusive row lock (SELECT ... FOR UPDATE) on the matched rows.
     *
     * Only meaningful inside a transaction(): the lock is held until the
     * surrounding transaction commits or rolls back. On engines without
     * row-lock syntax the dialect emits nothing, so gate on
     * supports(Capability::ROW_LOCK) before relying on it.
     */
    public function lockForUpdate(): static
    {
        return $this->copy(function (self $q): void {
            $q->lockMode = 'update';
        });
    }

    /**
     * Acquire a shared row lock (SELECT ... FOR SHARE): concurrent reads stay
     * allowed, writes block until the transaction ends. Same transaction and
     * capability caveats as lockForUpdate().
     */
    public function sharedLock(): static
    {
        return $this->copy(function (self $q): void {
            $q->lockMode = 'share';
        });
    }

    // ========================================================================
    // GROUP BY / HAVING / UNION
    // ========================================================================

    public function groupBy(string ...$columns): static
    {
        return $this->copy(function (self $q) use ($columns): void {
            $q->groups = array_values(array_merge($q->groups, $columns));
        });
    }

    /**
     * Add a HAVING condition (filters grouped rows). having(col, value) is an
     * equality shortcut; having(col, op, value) is an explicit comparison.
     */
    public function having(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (func_num_args() < 3) {
            if (func_num_args() < 2) {
                throw new InvalidArgumentException('having() requires a value or a comparison operator and value.');
            }
            $value = $operator;
            $sqlOperator = Operator::EQ->value;
        } else {
            $sqlOperator = $this->resolveComparisonOperator($operator)->value;
        }

        $normalizedBoolean = $this->normalizeBoolean($boolean);

        return $this->copy(function (self $q) use ($column, $sqlOperator, $value, $normalizedBoolean): void {
            $q->havings[] = [
                'boolean' => $normalizedBoolean,
                'column' => $column,
                'operator' => $sqlOperator,
                'value' => $value,
            ];
        });
    }

    public function orHaving(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() < 2) {
            throw new InvalidArgumentException('orHaving() requires a value or a comparison operator and value.');
        }

        if (func_num_args() < 3) {
            return $this->having($column, Operator::EQ, $operator, 'or');
        }

        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * Append a UNION with another builder. Only the other builder's compiled
     * SQL + bindings are used; the combined statement executes on this
     * builder's connection. unionAll() keeps duplicate rows.
     */
    public function union(self $query, bool $all = false): static
    {
        return $this->copy(function (self $q) use ($query, $all): void {
            $q->unions[] = ['query' => $query, 'all' => $all];
        });
    }

    public function unionAll(self $query): static
    {
        return $this->union($query, true);
    }

    // ========================================================================
    // COMPILATION (OFF + ON)
    // ========================================================================

    /**
     * Compile the SELECT statement to SQL + positional bindings.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function compile(): array
    {
        $dialect = $this->dialect();

        $columns = ($this->distinct ? 'DISTINCT ' : '') . implode(', ', $this->columns);
        $sql = 'SELECT ' . $columns . ' FROM ' . $this->compileFrom($dialect);

        [$whereSql, $bindings] = $this->compileWheresBody();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $sql .= $this->compileGroups();

        [$havingSql, $havingBindings] = $this->compileHavingsBody();
        if ($havingSql !== '') {
            $sql .= ' HAVING ' . $havingSql;
            array_push($bindings, ...$havingBindings);
        }

        $sql .= $this->compileOrders();
        $sql .= $this->compileLimitOffset($dialect);
        $sql .= $this->compileLock($dialect);

        foreach ($this->unions as $union) {
            [$unionSql, $unionBindings] = $union['query']->compile();
            $sql .= ($union['all'] ? ' UNION ALL ' : ' UNION ') . $unionSql;
            if ($unionBindings !== []) {
                array_push($bindings, ...$unionBindings);
            }
        }

        return [$sql, $bindings];
    }

    public function toSql(): string
    {
        return $this->compile()[0];
    }

    /**
     * @return array<int, mixed>
     */
    public function getBindings(): array
    {
        return $this->compile()[1];
    }

    // ========================================================================
    // TERMINALS (ON mode only)
    // ========================================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->compile();

        return $this->requireConnection()->fetchAll($sql, $bindings);
    }

    /**
     * @return null|array<string, mixed>
     */
    public function first(): ?array
    {
        [$sql, $bindings] = $this->limit(1)->compile();

        return $this->requireConnection()->fetch($sql, $bindings);
    }

    /**
     * @return null|array<string, mixed>
     */
    public function find(mixed $id, string $column = 'id'): ?array
    {
        return $this->where($column, $id)->first();
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();

        return $row[$column] ?? null;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $columns = $key === null ? [$column] : [$column, $key];
        $rows = $this->select(...$columns)->get();

        $result = [];
        foreach ($rows as $row) {
            if ($key === null) {
                $result[] = $row[$column] ?? null;

                continue;
            }

            /** @var array-key $pluckKey */
            $pluckKey = $row[$key];
            $result[$pluckKey] = $row[$column] ?? null;
        }

        return $result;
    }

    public function count(): int
    {
        $connection = $this->requireConnection();
        $dialect = $this->dialect();

        [$whereSql, $bindings] = $this->compileWheresBody();
        $where = $whereSql !== '' ? ' WHERE ' . $whereSql : '';
        $from = $this->compileFrom($dialect);

        if ($this->distinct) {
            // COUNT(DISTINCT *) is invalid SQL; wrap the distinct projection in a
            // subquery so the count honours DISTINCT over the selected columns —
            // works for one column or many, portably across engines.
            $sql = sprintf(
                'SELECT COUNT(*) AS aggregate FROM (SELECT DISTINCT %s FROM %s%s) AS aggregate_sub',
                implode(', ', $this->columns),
                $from,
                $where,
            );
        } else {
            $sql = 'SELECT COUNT(*) AS aggregate FROM ' . $from . $where;
        }

        $row = $connection->fetch($sql, $bindings);

        return (int) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->limit(1)->get() !== [];
    }

    /**
     * @return Page<array<string, mixed>>
     */
    public function paginate(int $page, int $perPage): Page
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $total = $this->count();
        $items = $this->forPage($page, $perPage)->get();

        return new Page($items, $total, $page, $perPage, true);
    }

    /**
     * Insert a single record and return the new primary key.
     *
     * @param array<string, mixed> $values
     */
    public function insert(array $values): int
    {
        return $this->requireConnection()->insert($this->table, $values);
    }

    /**
     * Update all rows matching the current WHERE and return the affected count.
     *
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $connection = $this->requireConnection();
        $dialect = $this->dialect();

        $assignments = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $assignments[] = $column . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'UPDATE ' . $dialect->table($this->table) . ' SET ' . implode(', ', $assignments);

        [$whereSql, $whereBindings] = $this->compileWheresBody();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
            array_push($bindings, ...$whereBindings);
        }

        return $connection->execute($sql, $bindings);
    }

    /**
     * Delete all rows matching the current WHERE and return the affected count.
     */
    public function delete(): int
    {
        $connection = $this->requireConnection();
        $dialect = $this->dialect();

        $sql = 'DELETE FROM ' . $dialect->table($this->table);

        [$whereSql, $bindings] = $this->compileWheresBody();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        return $connection->execute($sql, $bindings);
    }

    /**
     * Insert a single record and return the new primary key (alias of insert()).
     *
     * @param array<string, mixed> $values
     */
    public function insertGetId(array $values): int
    {
        return $this->insert($values);
    }

    /**
     * Update rows matching $attributes, or insert $attributes merged with
     * $values when none match. Returns true when a row was inserted or updated.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        $query = $this;
        foreach ($attributes as $column => $value) {
            $query = $query->where($column, $value);
        }

        if (!$query->exists()) {
            $this->insert(array_merge($attributes, $values));

            return true;
        }

        if ($values === []) {
            return true;
        }

        return $query->update($values) > 0;
    }

    /**
     * Insert one or many rows, updating the conflicting columns when a row
     * collides on the $uniqueBy key(s). $rows may be a single row or a list of
     * rows sharing the same columns; $update defaults to every column that is
     * not part of $uniqueBy. Engine-specific SQL comes from the dialect
     * ({@see SqlDialectInterface::upsertClause()}); callers needing portability
     * can gate on supports(Capability::UPSERT). Returns the affected row count.
     *
     * @param array<int, array<string, mixed>>|array<string, mixed> $rows
     * @param list<string>|string                                   $uniqueBy
     * @param null|list<string>                                     $update
     */
    public function upsert(array $rows, array|string $uniqueBy, ?array $update = null): int
    {
        if ($rows === []) {
            return 0;
        }

        if (!array_is_list($rows)) {
            $rows = [$rows];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $columns = array_keys($rows[0]);
        $uniqueBy = is_string($uniqueBy) ? [$uniqueBy] : $uniqueBy;
        $update ??= array_values(array_diff($columns, $uniqueBy));

        $connection = $this->requireConnection();
        $dialect = $this->dialect();

        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valuesSql = implode(', ', array_fill(0, count($rows), $rowPlaceholder));

        $bindings = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s%s',
            $dialect->table($this->table),
            implode(', ', $columns),
            $valuesSql,
            $dialect->upsertClause($uniqueBy, $update),
        );

        return $connection->execute($sql, $bindings);
    }

    /**
     * Sum of a column over the matched rows (0 when none match).
     */
    public function sum(string $column): float|int
    {
        return $this->numericAggregate('SUM', $column) ?? 0;
    }

    /**
     * Average of a column over the matched rows (null when none match).
     */
    public function avg(string $column): float|int|null
    {
        return $this->numericAggregate('AVG', $column);
    }

    /**
     * Minimum value of a column over the matched rows (null when none match).
     */
    public function min(string $column): mixed
    {
        return $this->rawAggregate('MIN', $column);
    }

    /**
     * Maximum value of a column over the matched rows (null when none match).
     */
    public function max(string $column): mixed
    {
        return $this->rawAggregate('MAX', $column);
    }

    // ========================================================================
    // STREAMING (ON mode only)
    // ========================================================================

    /**
     * Stream the matched rows one at a time from a single query, keeping memory
     * flat. Requires a connection that supports {@see Capability::STREAMING}.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function cursor(): iterable
    {
        $connection = $this->requireConnection();

        if (!$connection->supports(Capability::STREAMING)) {
            throw new LogicException(
                'This connection does not support streaming cursors; use chunk() or lazy() instead.'
            );
        }

        [$sql, $bindings] = $this->compile();

        return $connection->cursor($sql, $bindings);
    }

    /**
     * Process the matched rows in pages of $size, invoking $callback with each
     * page (an array of rows). Return false from the callback to stop early:
     * chunk() then returns false; otherwise it returns true once every page is
     * processed.
     *
     * @param callable(array<int, array<string, mixed>>): mixed $callback
     */
    public function chunk(int $size, callable $callback): bool
    {
        $size = max(1, $size);
        $page = 1;

        do {
            $results = $this->forPage($page, $size)->get();
            $count = count($results);

            if ($count === 0) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            ++$page;
        } while ($count === $size);

        return true;
    }

    /**
     * Lazily yield the matched rows one at a time, fetching them in pages of
     * $chunkSize under the hood. Portable: no STREAMING capability required.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function lazy(int $chunkSize = 1000): iterable
    {
        $chunkSize = max(1, $chunkSize);
        $page = 1;

        do {
            $results = $this->forPage($page, $chunkSize)->get();
            $count = count($results);

            foreach ($results as $row) {
                yield $row;
            }

            ++$page;
        } while ($count === $chunkSize);
    }

    public function getConnection(): ?ConnectionAdapterInterface
    {
        return $this->connection;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    private function whereNested(Closure $callback, string $boolean): static
    {
        $nested = $callback(new static($this->table, $this->connection)); // @phpstan-ignore-line — designed for subclassing; subclasses keep the constructor shape

        if (!$nested instanceof self) {
            throw new InvalidArgumentException(
                'A where-group closure must return the QueryBuilder it received (the builder is immutable), '
                . 'e.g. where(fn (QueryBuilder $q) => $q->where(...)->orWhere(...)).'
            );
        }

        return $this->pushWhere([
            'type' => 'nested',
            'boolean' => $this->normalizeBoolean($boolean),
            'query' => $nested,
        ]);
    }

    // ========================================================================
    // INTERNAL
    // ========================================================================

    private function addJoin(string $type, string $table, string $first, string $operator, string $second): static
    {
        return $this->copy(function (self $q) use ($type, $table, $first, $operator, $second): void {
            $q->joins[] = [
                'type' => $type,
                'table' => $table,
                'first' => $first,
                'operator' => $operator,
                'second' => $second,
            ];
        });
    }

    /**
     * @param array<string, mixed> $where
     */
    private function pushWhere(array $where): static
    {
        return $this->copy(function (self $q) use ($where): void {
            $q->wheres[] = $where;
        });
    }

    private function compileFrom(SqlDialectInterface $dialect): string
    {
        $from = $dialect->table($this->table);

        foreach ($this->joins as $join) {
            $from .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                strtoupper($join['type']),
                $dialect->table($join['table']),
                $join['first'],
                $join['operator'],
                $join['second'],
            );
        }

        return $from;
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function compileWheresBody(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($this->wheres as $index => $where) {
            [$fragment, $fragmentBindings] = $this->compileWhere($where);
            $prefix = $index === 0 ? '' : strtoupper((string) $where['boolean']) . ' ';
            $parts[] = $prefix . $fragment;
            array_push($bindings, ...$fragmentBindings);
        }

        return [implode(' ', $parts), $bindings];
    }

    /**
     * @param array<string, mixed> $where
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function compileWhere(array $where): array
    {
        return match ($where['type']) {
            'basic' => $this->compileBasicWhere($where),
            'in' => $this->compileInWhere($where),
            'between' => $this->compileBetweenWhere($where),
            'null' => [sprintf('%s IS %sNULL', $where['column'], $where['not'] ? 'NOT ' : ''), []],
            'column' => [sprintf('%s %s %s', $where['first'], $where['operator'], $where['second']), []],
            'nested' => $this->compileNestedWhere($where),
            default => throw new LogicException(sprintf('Unknown where type "%s".', (string) $where['type'])),
        };
    }

    /**
     * @param array<string, mixed> $where
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function compileBasicWhere(array $where): array
    {
        $operator = $where['operator'];
        $sqlOperator = $operator instanceof Operator ? $operator->value : (string) $operator;

        return [sprintf('%s %s ?', $where['column'], $sqlOperator), [$where['value']]];
    }

    /**
     * @param array<string, mixed> $where
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function compileInWhere(array $where): array
    {
        /** @var array<int, mixed> $values */
        $values = $where['values'];

        if ($values === []) {
            // Empty IN: emit a self-contradictory (NOT IN -> always-true) predicate
            // so the query stays valid instead of throwing at compile time.
            return [$where['not'] ? '1 = 1' : '1 = 0', []];
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $keyword = $where['not'] ? 'NOT IN' : 'IN';

        return [sprintf('%s %s (%s)', $where['column'], $keyword, $placeholders), $values];
    }

    /**
     * @param array<string, mixed> $where
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function compileBetweenWhere(array $where): array
    {
        $keyword = $where['not'] ? 'NOT BETWEEN' : 'BETWEEN';

        return [sprintf('%s %s ? AND ?', $where['column'], $keyword), [$where['min'], $where['max']]];
    }

    /**
     * @param array<string, mixed> $where
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function compileNestedWhere(array $where): array
    {
        $nested = $where['query'];
        if (!$nested instanceof self) {
            throw new LogicException('Nested where must hold a QueryBuilder.');
        }

        [$body, $bindings] = $nested->compileWheresBody();

        return ['(' . $body . ')', $bindings];
    }

    private function compileOrders(): string
    {
        if ($this->orders === []) {
            return '';
        }

        $parts = array_map(
            static fn (array $order): string => $order['column'] . ' ' . $order['direction'],
            $this->orders,
        );

        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function compileLimitOffset(SqlDialectInterface $dialect): string
    {
        return $dialect->limitOffset($this->limit, $this->offset);
    }

    private function compileLock(SqlDialectInterface $dialect): string
    {
        return $this->lockMode === null ? '' : $dialect->lockClause($this->lockMode);
    }

    private function compileGroups(): string
    {
        return $this->groups === [] ? '' : ' GROUP BY ' . implode(', ', $this->groups);
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function compileHavingsBody(): array
    {
        if ($this->havings === []) {
            return ['', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($this->havings as $index => $having) {
            $prefix = $index === 0 ? '' : strtoupper($having['boolean']) . ' ';
            $parts[] = $prefix . sprintf('%s %s ?', $having['column'], $having['operator']);
            $bindings[] = $having['value'];
        }

        return [implode(' ', $parts), $bindings];
    }

    /**
     * Run a single-value aggregate (SUM/AVG/MIN/MAX) over the matched rows,
     * honouring the current WHERE, and return the raw driver value (or null).
     */
    private function rawAggregate(string $function, string $column): mixed
    {
        $connection = $this->requireConnection();
        $dialect = $this->dialect();

        [$whereSql, $bindings] = $this->compileWheresBody();
        $where = $whereSql !== '' ? ' WHERE ' . $whereSql : '';

        $sql = sprintf('SELECT %s(%s) AS aggregate FROM %s%s', $function, $column, $this->compileFrom($dialect), $where);

        $row = $connection->fetch($sql, $bindings);

        return $row['aggregate'] ?? null;
    }

    /**
     * Like {@see rawAggregate()} but coerces the driver value to int/float
     * (drivers may return numeric strings); null when no rows matched.
     */
    private function numericAggregate(string $function, string $column): float|int|null
    {
        $value = $this->rawAggregate($function, $column);

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') || str_contains(strtolower($value), 'e')
                ? (float) $value
                : (int) $value;
        }

        return null;
    }

    private function resolveComparisonOperator(mixed $operator): Operator
    {
        if ($operator instanceof Operator) {
            $resolved = $operator;
        } else {
            $candidate = (string) $operator;
            $resolved = Operator::tryFrom($candidate) ?? Operator::tryFrom(strtoupper($candidate));
        }

        if (!$resolved instanceof Operator || !isset(self::COMPARISON_OPERATORS[$resolved->value])) {
            throw new InvalidArgumentException(
                'where() supports comparison operators only (=, <>, >, >=, <, <=, LIKE); '
                . 'use whereIn/whereNotIn, whereBetween or whereNull for set, range and null predicates.'
            );
        }

        return $resolved;
    }

    private function normalizeBoolean(string $boolean): string
    {
        $normalized = strtolower($boolean);

        return $normalized === 'or' ? 'or' : 'and';
    }

    private function dialect(): SqlDialectInterface
    {
        return $this->connection?->dialect() ?? new StandardSqlDialect();
    }

    private function requireConnection(): ConnectionAdapterInterface
    {
        if (!$this->connection instanceof ConnectionAdapterInterface) {
            throw new LogicException(
                'This query builder is in OFF mode (no connection). Use compile()/toSql() to inspect SQL, '
                . 'or build it with on()/a Model to execute terminals.'
            );
        }

        return $this->connection;
    }

    /**
     * @param Closure(static): void $modifier
     */
    private function copy(Closure $modifier): static
    {
        $clone = clone $this;
        $modifier($clone);

        return $clone;
    }
}
