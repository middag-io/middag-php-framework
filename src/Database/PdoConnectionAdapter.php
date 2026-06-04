<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Database;

use Generator;
use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Database\Enum\Capability;
use Middag\Framework\Exception\MiddagPersistenceException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * PDO-backed ConnectionAdapter for standalone usage.
 *
 * Suitable for any ANSI-compatible RDBMS (MySQL, PostgreSQL, SQLite). Platform
 * adapters (Moodle, WordPress) wrap their native DB APIs instead. Reports the
 * conservative ANSI baseline through supports(): transactions and streaming
 * only; RETURNING/UPSERT/JSON/schema-diff are left to engine-specific adapters.
 *
 * @api
 */
final readonly class PdoConnectionAdapter implements ConnectionAdapterInterface
{
    public function __construct(
        private PDO $pdo,
        private SqlDialectInterface $dialect = new StandardSqlDialect(),
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function supports(Capability $feature): bool
    {
        return match ($feature) {
            Capability::TRANSACTIONS, Capability::STREAMING => true,
            default => false,
        };
    }

    public function dialect(): SqlDialectInterface
    {
        return $this->dialect;
    }

    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindAndExecute($stmt, $params);

            return $stmt->rowCount();
        } catch (PDOException $pdoException) {
            throw new MiddagPersistenceException('SQL execute failed: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindAndExecute($stmt, $params);
            $row = $stmt->fetch();

            return $row !== false ? $row : null;
        } catch (PDOException $pdoException) {
            throw new MiddagPersistenceException('SQL fetch failed: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindAndExecute($stmt, $params);

            return $stmt->fetchAll();
        } catch (PDOException $pdoException) {
            throw new MiddagPersistenceException('SQL fetchAll failed: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $work($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();

            throw $throwable;
        }
    }

    public function insert(string $table, array $record): int
    {
        if ($record === []) {
            throw new MiddagPersistenceException('SQL insert failed: empty record.');
        }

        $columns = array_keys($record);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindAndExecute($stmt, $record);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $pdoException) {
            throw new MiddagPersistenceException('SQL insert failed: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    public function update(string $table, array $record): void
    {
        if (!isset($record['id'])) {
            throw new MiddagPersistenceException('SQL update failed: record has no id.');
        }

        $id = $record['id'];
        unset($record['id']);

        if ($record === []) {
            return;
        }

        $assignments = array_map(static fn (string $column): string => sprintf('%s = :%s', $column, $column), array_keys($record));

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $table, implode(', ', $assignments));

        $params = $record;
        $params['id'] = $id;

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindAndExecute($stmt, $params);
        } catch (PDOException $pdoException) {
            throw new MiddagPersistenceException('SQL update failed: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    public function delete(string $table, array $conditions): void
    {
        if ($conditions === []) {
            // Empty conditions would compile to `DELETE FROM <table>` and wipe
            // every row. Refuse it (consistent with the insert/update guards);
            // an intentional bulk delete goes through the query builder.
            throw new MiddagPersistenceException('SQL delete failed: refusing to delete all rows (empty conditions).');
        }

        [$where, $params] = $this->buildWhere($conditions);

        $sql = sprintf('DELETE FROM %s%s', $table, $where);

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindAndExecute($stmt, $params);
        } catch (PDOException $pdoException) {
            throw new MiddagPersistenceException('SQL delete failed: ' . $pdoException->getMessage(), 0, $pdoException);
        }
    }

    public function find(string $table, array $conditions): ?array
    {
        [$where, $params] = $this->buildWhere($conditions);

        return $this->fetch(sprintf('SELECT * FROM %s%s', $table, $where), $params);
    }

    public function findAll(string $table, array $conditions = []): array
    {
        [$where, $params] = $this->buildWhere($conditions);

        return $this->fetchAll(sprintf('SELECT * FROM %s%s', $table, $where), $params);
    }

    public function cursor(string $sql, array $params = []): iterable
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindAndExecute($stmt, $params);
        } catch (PDOException $pdoException) {
            throw new MiddagPersistenceException('SQL cursor failed: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        return (static function () use ($stmt): Generator {
            while (($row = $stmt->fetch()) !== false) {
                yield $row;
            }
        })();
    }

    /**
     * Build a parameterized WHERE clause from equality conditions.
     *
     * @param array<string, mixed> $conditions
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $conditions): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $clauses = array_map(static fn (string $column): string => sprintf('%s = :%s', $column, $column), array_keys($conditions));

        return [' WHERE ' . implode(' AND ', $clauses), $conditions];
    }

    /**
     * Bind params to a prepared statement type-aware, then execute.
     *
     * Passing the array straight to PDOStatement::execute() binds every value
     * as PARAM_STR; numeric comparisons against expressions without column
     * affinity (e.g. `HAVING COUNT(*) > ?`) then fail on SQLite/PostgreSQL,
     * which order numerics before text. Detect int/bool/null and bind with the
     * matching PDO type. Positional params (int keys) bind 1-based; named
     * params (string keys) bind as `:name`.
     *
     * @param array<int|string, mixed> $params
     */
    private function bindAndExecute(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':' . $key;
            $stmt->bindValue($parameter, $value, $this->paramType($value));
        }

        $stmt->execute();
    }

    private function paramType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
