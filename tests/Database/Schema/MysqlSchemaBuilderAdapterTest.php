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
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
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
    #[DoesNotPerformAssertions]
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
    public function nonArrayKeyEntriesAreSkippedWhenResolvingThePrimaryKey(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 't',
            'columns' => [['name' => 'a', 'type' => 'int', 'notnull' => true]],
            // A malformed (non-array) keys entry must be skipped, not fatal.
            'keys' => ['garbage', ['type' => 'primary', 'fields' => ['a']]],
        ]);

        self::assertStringContainsString('PRIMARY KEY (a)', $connection->queries[0]);
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

    #[Test]
    public function dropTableEmitsIfExists(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->dropTable('demo');

        self::assertSame(['DROP TABLE IF EXISTS demo'], $connection->queries);
    }

    #[Test]
    public function addColumnEmitsAlterTableAddColumn(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->addColumn('demo', ['name' => 'slug', 'type' => 'varchar', 'length' => 64]);

        self::assertStringStartsWith('ALTER TABLE demo ADD COLUMN slug VARCHAR(64)', $connection->queries[0]);
    }

    #[Test]
    public function dropColumnEmitsAlterTableDropColumn(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->dropColumn('demo', 'slug');

        self::assertSame(['ALTER TABLE demo DROP COLUMN slug'], $connection->queries);
    }

    #[Test]
    public function addIndexEmitsCreateIndex(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->addIndex('demo', ['name' => 'idx_slug', 'fields' => ['slug']]);

        self::assertSame(['CREATE INDEX idx_slug ON demo (slug)'], $connection->queries);
    }

    #[Test]
    public function addUniqueIndexEmitsUniqueKeyword(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->addIndex('demo', ['name' => 'uq_slug', 'columns' => ['slug'], 'unique' => true]);

        self::assertSame(['CREATE UNIQUE INDEX uq_slug ON demo (slug)'], $connection->queries);
    }

    #[Test]
    public function mapTypeTranslatesEveryDescriptorType(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 'types',
            'columns' => [
                ['name' => 'c_int', 'type' => 'int'],
                ['name' => 'c_int_len', 'type' => 'integer', 'length' => 11],
                ['name' => 'c_bigint', 'type' => 'bigint'],
                ['name' => 'c_smallint', 'type' => 'smallint'],
                ['name' => 'c_char', 'type' => 'char'],
                ['name' => 'c_varchar', 'type' => 'varchar', 'length' => 64],
                ['name' => 'c_text', 'type' => 'text'],
                ['name' => 'c_longtext', 'type' => 'longtext'],
                ['name' => 'c_float', 'type' => 'float'],
                ['name' => 'c_number', 'type' => 'number'],
                ['name' => 'c_decimal', 'type' => 'decimal'],
                ['name' => 'c_numeric', 'type' => 'numeric', 'length' => 8, 'decimals' => 4],
                ['name' => 'c_datetime', 'type' => 'datetime'],
                ['name' => 'c_date', 'type' => 'date'],
                ['name' => 'c_boolean', 'type' => 'boolean'],
                ['name' => 'c_bool', 'type' => 'bool'],
                ['name' => 'c_blob', 'type' => 'blob'],
                ['name' => 'c_binary', 'type' => 'binary'],
                ['name' => 'c_unknown', 'type' => 'weird'],
                ['name' => 'c_untyped'],
            ],
        ]);

        $sql = $connection->queries[0];
        self::assertStringContainsString('c_int INT ', $sql);
        self::assertStringContainsString('c_int_len INT(11)', $sql);
        self::assertStringContainsString('c_bigint BIGINT', $sql);
        self::assertStringContainsString('c_smallint SMALLINT', $sql);
        self::assertStringContainsString('c_char VARCHAR(255)', $sql);
        self::assertStringContainsString('c_varchar VARCHAR(64)', $sql);
        self::assertStringContainsString('c_text TEXT', $sql);
        self::assertStringContainsString('c_longtext TEXT', $sql);
        self::assertStringContainsString('c_float DOUBLE', $sql);
        self::assertStringContainsString('c_number DOUBLE', $sql);
        self::assertStringContainsString('c_decimal DECIMAL(10,2)', $sql);
        self::assertStringContainsString('c_numeric DECIMAL(8,4)', $sql);
        self::assertStringContainsString('c_datetime DATETIME', $sql);
        self::assertStringContainsString('c_date DATE', $sql);
        self::assertStringContainsString('c_boolean TINYINT(1)', $sql);
        self::assertStringContainsString('c_bool TINYINT(1)', $sql);
        self::assertStringContainsString('c_blob BLOB', $sql);
        self::assertStringContainsString('c_binary BLOB', $sql);
        self::assertStringContainsString('c_unknown TEXT', $sql);
        self::assertStringContainsString('c_untyped TEXT', $sql);
    }

    #[Test]
    public function columnDdlEmitsNotNullNumericAndStringDefaultsAndAutoIncrement(): void
    {
        $connection = $this->capturingConnection();
        (new MysqlSchemaBuilderAdapter($connection))->createTable([
            'name' => 'defaults',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true, 'default' => 99],
                ['name' => 'status', 'type' => 'int', 'notnull' => true, 'default' => 0],
                ['name' => 'label', 'type' => 'varchar', 'default' => "O'Brien"],
            ],
        ]);

        $sql = $connection->queries[0];
        // Sequence column carries AUTO_INCREMENT and ignores its default.
        self::assertStringContainsString('id BIGINT NULL AUTO_INCREMENT', $sql);
        self::assertStringNotContainsString('DEFAULT 99', $sql);
        // Numeric default is emitted unquoted; NOT NULL honoured.
        self::assertStringContainsString('status INT NOT NULL', $sql);
        self::assertStringContainsString('DEFAULT 0', $sql);
        // String default is quoted and escaped.
        self::assertStringContainsString("DEFAULT 'O\\'Brien'", $sql);
    }

    #[Test]
    public function tableExistsProbesInformationSchema(): void
    {
        self::assertTrue((new MysqlSchemaBuilderAdapter($this->fetchingConnection(['1' => 1])))->tableExists('demo'));
        self::assertFalse((new MysqlSchemaBuilderAdapter($this->fetchingConnection(null)))->tableExists('demo'));
    }

    #[Test]
    public function tableExistsFallsBackToProbeWhenInformationSchemaUnavailable(): void
    {
        // fetch() throws (no information_schema); the execute() probe then decides.
        $present = new class implements ConnectionInterface {
            public function execute(string $sql, array $params = []): int
            {
                return 0; // probe succeeds → table present
            }

            public function fetch(string $sql, array $params = []): ?array
            {
                throw new RuntimeException('no such table: information_schema.tables');
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
        self::assertTrue((new MysqlSchemaBuilderAdapter($present))->tableExists('demo'));

        $absent = new class implements ConnectionInterface {
            public function execute(string $sql, array $params = []): int
            {
                throw new RuntimeException('no such table: demo'); // probe fails → absent
            }

            public function fetch(string $sql, array $params = []): ?array
            {
                throw new RuntimeException('no such table: information_schema.tables');
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
        self::assertFalse((new MysqlSchemaBuilderAdapter($absent))->tableExists('demo'));
    }

    #[Test]
    public function columnExistsProbesInformationSchemaAndFailsClosed(): void
    {
        self::assertTrue((new MysqlSchemaBuilderAdapter($this->fetchingConnection(['1' => 1])))->columnExists('demo', 'slug'));

        $throwing = new class implements ConnectionInterface {
            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }

            public function fetch(string $sql, array $params = []): ?array
            {
                throw new RuntimeException('no information_schema');
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
        self::assertFalse((new MysqlSchemaBuilderAdapter($throwing))->columnExists('demo', 'slug'));
    }

    /**
     * A connection whose fetch() returns a fixed row (or null), for the
     * information_schema existence probes.
     *
     * @param null|array<string, mixed> $row
     */
    private function fetchingConnection(?array $row): ConnectionInterface
    {
        return new class($row) implements ConnectionInterface {
            /** @param null|array<string, mixed> $row */
            public function __construct(private readonly ?array $row) {}

            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }

            public function fetch(string $sql, array $params = []): ?array
            {
                return $this->row;
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
