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

use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\Schema\SqliteSchemaBuilderAdapter;
use Middag\Framework\Exception\MiddagPersistenceException;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
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

    private function adapter(): SqliteSchemaBuilderAdapter
    {
        return new SqliteSchemaBuilderAdapter(new PdoConnectionAdapter($this->pdo()));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
