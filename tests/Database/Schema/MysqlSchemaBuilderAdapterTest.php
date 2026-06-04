<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Database\Schema;

use Middag\Framework\Database\Contract\ConnectionInterface;
use Middag\Framework\Database\Schema\MysqlSchemaBuilderAdapter;
use Middag\Framework\Exception\MiddagPersistenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * dropIndex must emit MySQL-valid DDL (no `IF EXISTS`, which MySQL 8
 * rejects on DROP INDEX) and stay idempotent by swallowing the missing-index error.
 *
 * @internal
 */
#[CoversClass(MysqlSchemaBuilderAdapter::class)]
final class MysqlSchemaBuilderAdapterTest extends TestCase
{
    #[Test]
    public function dropIndexEmitsMysqlValidDdlWithoutIfExists(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->dropIndex('demo_tasks', 'idx_title');

        self::assertSame(['DROP INDEX idx_title ON demo_tasks'], $connection->queries);
        self::assertStringNotContainsStringIgnoringCase('IF EXISTS', $connection->queries[0]);
    }

    #[Test]
    public function dropIndexIsIdempotentWhenIndexAbsent(): void
    {
        $connection = new class implements ConnectionInterface {
            public function execute(string $sql, array $params = []): int
            {
                throw new RuntimeException("Can't DROP 'idx_title'; check that it exists");
            }

            public function fetch(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function fetchAll(string $sql, array $params = []): array
            {
                return [];
            }

            public function transaction(callable $work): mixed
            {
                return $work();
            }
        };

        (new MysqlSchemaBuilderAdapter($connection))->dropIndex('demo_tasks', 'idx_title');

        // No exception bubbled up — the drop is a no-op when the index is gone.
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function createTableEmitsIndexFromFieldsKey(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 'demo',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'slug', 'type' => 'varchar'],
            ],
            'indexes' => [
                ['name' => 'idx_demo_slug', 'fields' => ['slug']],
            ],
        ]);

        self::assertStringContainsString('INDEX idx_demo_slug (slug)', $connection->queries[0]);
        self::assertStringContainsString('PRIMARY KEY (id)', $connection->queries[0]);
    }

    #[Test]
    public function createTableToleratesColumnsKeyForIndex(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 'demo',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'a', 'type' => 'int'],
                ['name' => 'b', 'type' => 'int'],
            ],
            // SQLite-style 'columns' key must work on the MySQL adapter too.
            'indexes' => [
                ['name' => 'idx_demo_ab', 'columns' => ['a', 'b']],
            ],
        ]);

        self::assertStringContainsString('INDEX idx_demo_ab (a, b)', $connection->queries[0]);
    }

    #[Test]
    public function createTableEmitsCompositePrimaryKeyFromKeys(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 'pivot',
            'columns' => [
                ['name' => 'a_id', 'type' => 'bigint', 'notnull' => true],
                ['name' => 'b_id', 'type' => 'bigint', 'notnull' => true],
            ],
            'keys' => [
                ['type' => 'primary', 'fields' => ['a_id', 'b_id']],
            ],
        ]);

        self::assertStringContainsString('PRIMARY KEY (a_id, b_id)', $connection->queries[0]);
    }

    #[Test]
    public function addIndexThrowsWhenIndexHasNoColumns(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        (new MysqlSchemaBuilderAdapter($this->capturingConnection()))->addIndex('demo', ['name' => 'idx_bad']);
    }

    #[Test]
    public function untypedKeyEntryIsNotEmittedAsPrimaryKey(): void
    {
        // A foreign-key-shaped entry WITHOUT an explicit type must never be
        // mis-emitted as a PRIMARY KEY (only type => 'primary' counts).
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 'rel',
            'columns' => [
                ['name' => 'user_id', 'type' => 'bigint', 'notnull' => true],
                ['name' => 'role_id', 'type' => 'bigint', 'notnull' => true],
            ],
            'keys' => [
                ['name' => 'fk_user', 'fields' => ['user_id'], 'reftable' => 'users'],
            ],
        ]);

        self::assertStringNotContainsString('PRIMARY KEY', $connection->queries[0]);
    }

    #[Test]
    public function realPrimaryKeyWinsRegardlessOfKeyOrderAndForeignEntries(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 'rel',
            'columns' => [
                ['name' => 'a_id', 'type' => 'bigint', 'notnull' => true],
                ['name' => 'b_id', 'type' => 'bigint', 'notnull' => true],
            ],
            'keys' => [
                ['name' => 'fk_a', 'fields' => ['a_id'], 'reftable' => 'a_table'],   // untyped FK first
                ['type' => 'primary', 'fields' => ['a_id', 'b_id']],
                ['type' => 'foreign', 'fields' => ['b_id'], 'reftable' => 'b_table'],
            ],
        ]);

        self::assertStringContainsString('PRIMARY KEY (a_id, b_id)', $connection->queries[0]);
    }

    #[Test]
    public function sequenceColumnOwnsPrimaryKeyWithoutEmittingASecondClause(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 't',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'a', 'type' => 'int'],
            ],
            'keys' => [
                ['type' => 'primary', 'fields' => ['a']],
            ],
        ]);

        self::assertSame(1, substr_count($connection->queries[0], 'PRIMARY KEY'));
        self::assertStringContainsString('PRIMARY KEY (id)', $connection->queries[0]);
    }

    /**
     * @return ConnectionInterface&object{queries: string[]}
     */
    private function capturingConnection(): ConnectionInterface
    {
        return new class implements ConnectionInterface {
            /** @var string[] */
            public array $queries = [];

            public function execute(string $sql, array $params = []): int
            {
                $this->queries[] = $sql;

                return 0;
            }

            public function fetch(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function fetchAll(string $sql, array $params = []): array
            {
                return [];
            }

            public function transaction(callable $work): mixed
            {
                return $work();
            }
        };
    }
}
