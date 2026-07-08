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
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\Schema\SqliteSchemaBuilderAdapter;
use Middag\Framework\Exception\MiddagPersistenceException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(SqliteSchemaBuilderAdapter::class)]
final class SqliteSchemaBuilderAdapterTest extends TestCase
{
    public function testCreateTableEmitsSqliteAutoincrementForSequenceColumn(): void
    {
        $adapter = $this->adapter();
        $adapter->createTable([
            'name' => 'demo_tasks',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
                ['name' => 'title', 'type' => 'varchar', 'length' => 255, 'notnull' => true],
                ['name' => 'done', 'type' => 'int', 'notnull' => true, 'default' => 0],
            ],
        ]);

        self::assertTrue($adapter->tableExists('demo_tasks'));
        self::assertTrue($adapter->columnExists('demo_tasks', 'title'));
        self::assertFalse($adapter->tableExists('missing_table'));
    }

    public function testCreateTableIsIdempotentViaIfNotExists(): void
    {
        $adapter = $this->adapter();
        $descriptor = [
            'name' => 'idem',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'notnull' => true, 'sequence' => true],
            ],
        ];

        $adapter->createTable($descriptor);
        $adapter->createTable($descriptor);

        self::assertTrue($adapter->tableExists('idem'));
    }

    public function testDropTableRemovesTable(): void
    {
        $adapter = $this->adapter();
        $adapter->createTable([
            'name' => 'drop_me',
            'columns' => [['name' => 'id', 'type' => 'bigint', 'sequence' => true]],
        ]);

        $adapter->dropTable('drop_me');
        self::assertFalse($adapter->tableExists('drop_me'));
    }

    public function testCreateTableEmitsDeclaredIndexes(): void
    {
        $pdo = $this->pdo();
        $adapter = new SqliteSchemaBuilderAdapter(new PdoConnectionAdapter($pdo));
        $adapter->createTable([
            'name' => 'idx_demo',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'slug', 'type' => 'varchar', 'length' => 64],
            ],
            'indexes' => [
                // Table-local name; the adapter qualifies it with the table.
                ['name' => 'slug', 'columns' => ['slug'], 'unique' => true],
            ],
        ]);

        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('idx_demo_slug', $names);
    }

    public function testAddIndexToleratesFieldsKey(): void
    {
        $pdo = $this->pdo();
        $adapter = new SqliteSchemaBuilderAdapter(new PdoConnectionAdapter($pdo));
        $adapter->createTable([
            'name' => 'tol',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'a', 'type' => 'int'],
            ],
        ]);
        // MySQL-style 'fields' key must work on the SQLite adapter too.
        $adapter->addIndex('tol', ['name' => 'active', 'fields' => ['a']]);

        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('tol_active', $names);
    }

    public function testDeclaredIndexNamesAreQualifiedPerTableToAvoidCollision(): void
    {
        $pdo = $this->pdo();
        $adapter = new SqliteSchemaBuilderAdapter(new PdoConnectionAdapter($pdo));

        // Two tables declare an index with the SAME bare name ('status') —
        // idiomatic for MySQL/Moodle. SQLite namespaces indexes per schema, so
        // without qualification the second CREATE INDEX IF NOT EXISTS would
        // silently no-op and the second table would lose its index.
        foreach (['orders', 'invoices'] as $table) {
            $adapter->createTable([
                'name' => $table,
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                    ['name' => 'status', 'type' => 'int'],
                ],
                'indexes' => [
                    ['name' => 'status', 'columns' => ['status']],
                ],
            ]);
        }

        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('orders_status', $names);
        self::assertContains('invoices_status', $names);
    }

    public function testCreateTableEmitsCompositePrimaryKeyFromKeys(): void
    {
        $pdo = $this->pdo();
        $adapter = new SqliteSchemaBuilderAdapter(new PdoConnectionAdapter($pdo));
        $adapter->createTable([
            'name' => 'pivot',
            'columns' => [
                ['name' => 'a_id', 'type' => 'bigint', 'notnull' => true],
                ['name' => 'b_id', 'type' => 'bigint', 'notnull' => true],
            ],
            'keys' => [
                ['type' => 'primary', 'fields' => ['a_id', 'b_id']],
            ],
        ]);

        $pk = [];
        foreach ($pdo->query('PRAGMA table_info(pivot)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int) $row['pk'] > 0) {
                $pk[(int) $row['pk']] = (string) $row['name'];
            }
        }
        ksort($pk);

        self::assertSame(['a_id', 'b_id'], array_values($pk));
    }

    public function testAddIndexThrowsWhenIndexHasNoColumns(): void
    {
        $adapter = $this->adapter();

        $this->expectException(MiddagPersistenceException::class);
        $adapter->addIndex('whatever', ['name' => 'idx_bad']);
    }

    public function testAddColumnAltersTableAndColumnBecomesVisible(): void
    {
        $adapter = $this->adapter();
        $adapter->createTable([
            'name' => 'grows',
            'columns' => [['name' => 'id', 'type' => 'bigint', 'sequence' => true]],
        ]);

        $adapter->addColumn('grows', ['name' => 'slug', 'type' => 'varchar', 'length' => 64]);

        self::assertTrue($adapter->columnExists('grows', 'slug'));
    }

    public function testDropColumnEmitsAlterTableDropColumn(): void
    {
        $connection = $this->capturingConnection();
        (new SqliteSchemaBuilderAdapter($connection))->dropColumn('grows', 'slug');

        self::assertSame(['ALTER TABLE grows DROP COLUMN slug'], $connection->queries);
    }

    public function testDropIndexEmitsDropIndexIfExists(): void
    {
        $connection = $this->capturingConnection();
        (new SqliteSchemaBuilderAdapter($connection))->dropIndex('grows', 'idx_slug');

        // SQLite (unlike MySQL) honours IF EXISTS on DROP INDEX, keeping it idempotent.
        self::assertSame(['DROP INDEX IF EXISTS idx_slug'], $connection->queries);
    }

    public function testAddIndexAutoGeneratesTableQualifiedNameWhenUnnamed(): void
    {
        $connection = $this->capturingConnection();
        // No 'name' key → adapter falls back to a table-qualified idx_<table>_<cols> name.
        (new SqliteSchemaBuilderAdapter($connection))->addIndex('grows', ['columns' => ['a', 'b']]);

        self::assertSame(
            ['CREATE  INDEX IF NOT EXISTS idx_grows_a_b ON grows (a, b)'],
            $connection->queries,
        );
    }

    public function testColumnDdlEmitsNullabilityNumericAndEscapedStringDefaults(): void
    {
        $connection = $this->capturingConnection();
        (new SqliteSchemaBuilderAdapter($connection))->createTable([
            'name' => 'defaults',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'qty', 'type' => 'int', 'notnull' => true, 'default' => 0],
                ['name' => 'label', 'type' => 'varchar', 'default' => "O'Brien"],
                ['name' => 'note', 'type' => 'text'],
            ],
        ]);

        $sql = $connection->queries[0];
        self::assertStringContainsString('id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        // NOT NULL honoured; numeric default emitted unquoted.
        self::assertStringContainsString('qty INTEGER NOT NULL DEFAULT 0', $sql);
        // Nullable column with a quoted + escaped string default.
        self::assertStringContainsString("label TEXT NULL DEFAULT 'O\\'Brien'", $sql);
        // No 'default' key → the DEFAULT clause is absent and trimmed away.
        self::assertStringContainsString('note TEXT NULL', $sql);
        self::assertStringNotContainsString('note TEXT NULL DEFAULT', $sql);
    }

    public function testMapTypeTranslatesEveryDescriptorTypeToSqliteAffinity(): void
    {
        $connection = $this->capturingConnection();
        (new SqliteSchemaBuilderAdapter($connection))->createTable([
            'name' => 'types',
            'columns' => [
                ['name' => 'c_int', 'type' => 'int'],
                ['name' => 'c_integer', 'type' => 'integer'],
                ['name' => 'c_bigint', 'type' => 'bigint'],
                ['name' => 'c_smallint', 'type' => 'smallint'],
                ['name' => 'c_char', 'type' => 'char'],
                ['name' => 'c_varchar', 'type' => 'varchar'],
                ['name' => 'c_text', 'type' => 'text'],
                ['name' => 'c_longtext', 'type' => 'longtext'],
                ['name' => 'c_float', 'type' => 'float'],
                ['name' => 'c_number', 'type' => 'number'],
                ['name' => 'c_decimal', 'type' => 'decimal'],
                ['name' => 'c_numeric', 'type' => 'numeric'],
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
        self::assertStringContainsString('c_int INTEGER', $sql);
        self::assertStringContainsString('c_integer INTEGER', $sql);
        self::assertStringContainsString('c_bigint INTEGER', $sql);
        self::assertStringContainsString('c_smallint INTEGER', $sql);
        self::assertStringContainsString('c_char TEXT', $sql);
        self::assertStringContainsString('c_varchar TEXT', $sql);
        self::assertStringContainsString('c_text TEXT', $sql);
        self::assertStringContainsString('c_longtext TEXT', $sql);
        self::assertStringContainsString('c_float REAL', $sql);
        self::assertStringContainsString('c_number REAL', $sql);
        self::assertStringContainsString('c_decimal REAL', $sql);
        self::assertStringContainsString('c_numeric REAL', $sql);
        self::assertStringContainsString('c_datetime TEXT', $sql);
        self::assertStringContainsString('c_date TEXT', $sql);
        self::assertStringContainsString('c_boolean INTEGER', $sql);
        self::assertStringContainsString('c_bool INTEGER', $sql);
        self::assertStringContainsString('c_blob BLOB', $sql);
        self::assertStringContainsString('c_binary BLOB', $sql);
        self::assertStringContainsString('c_unknown TEXT', $sql);
        self::assertStringContainsString('c_untyped TEXT', $sql);
    }

    public function testNonArrayKeyEntriesAreSkippedWhenResolvingPrimaryKey(): void
    {
        $connection = $this->capturingConnection();
        (new SqliteSchemaBuilderAdapter($connection))->createTable([
            'name' => 't',
            'columns' => [['name' => 'a', 'type' => 'int', 'notnull' => true]],
            // A malformed (non-array) keys entry must be skipped, not fatal.
            'keys' => ['garbage', ['type' => 'primary', 'fields' => ['a']]],
        ]);

        self::assertStringContainsString('PRIMARY KEY (a)', $connection->queries[0]);
    }

    public function testUntypedKeyEntryIsNotEmittedAsPrimaryKey(): void
    {
        // A foreign-key-shaped entry WITHOUT an explicit type must never be
        // mis-emitted as a PRIMARY KEY (only type => 'primary' counts).
        $connection = $this->capturingConnection();
        (new SqliteSchemaBuilderAdapter($connection))->createTable([
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

    public function testSequenceColumnOwnsPrimaryKeyWithoutASecondCompositeClause(): void
    {
        // With a sequence column, the inline INTEGER PRIMARY KEY AUTOINCREMENT
        // wins and the descriptor's composite keys are not re-emitted.
        $connection = $this->capturingConnection();
        (new SqliteSchemaBuilderAdapter($connection))->createTable([
            'name' => 't',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'a', 'type' => 'int'],
            ],
            'keys' => [
                ['type' => 'primary', 'fields' => ['a']],
            ],
        ]);

        $sql = $connection->queries[0];
        self::assertSame(1, substr_count($sql, 'PRIMARY KEY'));
        self::assertStringContainsString('id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    }

    public function testColumnExistsReturnsFalseForAbsentColumn(): void
    {
        $adapter = $this->adapter();
        $adapter->createTable([
            'name' => 'present',
            'columns' => [['name' => 'id', 'type' => 'bigint', 'sequence' => true]],
        ]);

        self::assertFalse($adapter->columnExists('present', 'ghost'));
    }

    public function testTableExistsFailsClosedWhenConnectionThrows(): void
    {
        $adapter = new SqliteSchemaBuilderAdapter($this->throwingConnection());

        self::assertFalse($adapter->tableExists('demo'));
    }

    public function testColumnExistsFailsClosedWhenConnectionThrows(): void
    {
        $adapter = new SqliteSchemaBuilderAdapter($this->throwingConnection());

        self::assertFalse($adapter->columnExists('demo', 'slug'));
    }

    private function adapter(): SqliteSchemaBuilderAdapter
    {
        return new SqliteSchemaBuilderAdapter(new PdoConnectionAdapter($this->pdo()));
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

    /**
     * A connection whose read probes always throw, exercising the fail-closed
     * catch branches of tableExists()/columnExists().
     */
    private function throwingConnection(): ConnectionInterface
    {
        return new class implements ConnectionInterface {
            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }

            public function fetch(string $sql, array $params = []): ?array
            {
                throw new RuntimeException('no sqlite_master');
            }

            public function fetchAll(string $sql, array $params = []): array
            {
                throw new RuntimeException('no such table');
            }

            public function transaction(callable $work): mixed
            {
                return $work();
            }
        };
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
