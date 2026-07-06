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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Middag\Framework\Database\Schema\DbalSchemaBuilderAdapter;
use Middag\Framework\Exception\MiddagPersistenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the adapter against a real (in-memory SQLite) DBAL connection, so the
 * descriptor→DBAL mapping and the introspect-and-alter flow are genuinely run.
 *
 * @internal
 */
#[CoversClass(DbalSchemaBuilderAdapter::class)]
final class DbalSchemaBuilderAdapterTest extends TestCase
{
    private Connection $connection;

    private DbalSchemaBuilderAdapter $adapter;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->adapter = new DbalSchemaBuilderAdapter($this->connection);
    }

    public function testCreateTableMaterializesColumnsAndIndex(): void
    {
        $this->adapter->createTable($this->descriptor());

        self::assertTrue($this->adapter->tableExists('middag_widget'));
        self::assertTrue($this->adapter->columnExists('middag_widget', 'id'));
        self::assertTrue($this->adapter->columnExists('middag_widget', 'title'));
        self::assertTrue(
            $this->connection->createSchemaManager()->introspectTable('middag_widget')->hasIndex('middag_widget_idx_widget_title'),
        );
    }

    public function testCreateTableIsIdempotent(): void
    {
        $this->adapter->createTable($this->descriptor());
        $this->adapter->createTable($this->descriptor());

        self::assertTrue($this->adapter->tableExists('middag_widget'));
    }

    public function testAddAndDropColumn(): void
    {
        $this->adapter->createTable($this->descriptor());

        $this->adapter->addColumn('middag_widget', ['name' => 'note', 'type' => 'text']);
        self::assertTrue($this->adapter->columnExists('middag_widget', 'note'));

        $this->adapter->dropColumn('middag_widget', 'note');
        self::assertFalse($this->adapter->columnExists('middag_widget', 'note'));
    }

    public function testAddAndDropIndex(): void
    {
        $this->adapter->createTable($this->descriptor());
        $schemaManager = $this->connection->createSchemaManager();

        $this->adapter->addIndex('middag_widget', ['name' => 'idx_widget_active', 'fields' => ['active']]);
        self::assertTrue($schemaManager->introspectTable('middag_widget')->hasIndex('idx_widget_active'));

        $this->adapter->dropIndex('middag_widget', 'idx_widget_active');
        self::assertFalse($schemaManager->introspectTable('middag_widget')->hasIndex('idx_widget_active'));
    }

    public function testDropTable(): void
    {
        $this->adapter->createTable($this->descriptor());
        self::assertTrue($this->adapter->tableExists('middag_widget'));

        $this->adapter->dropTable('middag_widget');
        self::assertFalse($this->adapter->tableExists('middag_widget'));
    }

    public function testColumnExistsFalseForMissingTable(): void
    {
        self::assertFalse($this->adapter->columnExists('nonexistent', 'whatever'));
    }

    public function testCreateTableToleratesColumnsKeyForIndex(): void
    {
        $this->adapter->createTable([
            'name' => 'widget2',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'label', 'type' => 'varchar', 'length' => 50],
            ],
            // SQLite-style 'columns' key must work on the DBAL adapter too.
            'indexes' => [
                ['name' => 'idx_widget2_label', 'columns' => ['label']],
            ],
        ]);

        self::assertTrue(
            $this->connection->createSchemaManager()->introspectTable('widget2')->hasIndex('widget2_idx_widget2_label'),
        );
    }

    public function testCreateTableEmitsCompositePrimaryKeyFromKeys(): void
    {
        $this->adapter->createTable([
            'name' => 'pivot',
            'columns' => [
                ['name' => 'a_id', 'type' => 'bigint', 'notnull' => true],
                ['name' => 'b_id', 'type' => 'bigint', 'notnull' => true],
            ],
            'keys' => [
                ['type' => 'primary', 'fields' => ['a_id', 'b_id']],
            ],
        ]);

        // Introspect via PRAGMA to avoid DBAL's deprecated getPrimaryKey() API.
        $pk = [];
        foreach ($this->connection->fetchAllAssociative('PRAGMA table_info(pivot)') as $row) {
            if ((int) $row['pk'] > 0) {
                $pk[(int) $row['pk']] = (string) $row['name'];
            }
        }
        ksort($pk);

        self::assertSame(['a_id', 'b_id'], array_values($pk));
    }

    public function testCreateTableThrowsWhenIndexHasNoColumns(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->adapter->createTable([
            'name' => 'broken',
            'columns' => [['name' => 'id', 'type' => 'bigint', 'sequence' => true]],
            'indexes' => [['name' => 'idx_broken_nope']],
        ]);
    }

    public function testCreateTableMapsEveryColumnTypeIncludingDecimalPrecision(): void
    {
        $this->adapter->createTable([
            'name' => 'types_dbal',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'c_int', 'type' => 'int'],
                ['name' => 'c_integer', 'type' => 'integer'],
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

        $table = $this->connection->createSchemaManager()->introspectTable('types_dbal');
        foreach (['c_int', 'c_smallint', 'c_char', 'c_decimal', 'c_numeric', 'c_datetime', 'c_boolean', 'c_blob', 'c_unknown', 'c_untyped'] as $column) {
            self::assertTrue($table->hasColumn($column), 'missing column ' . $column);
        }
    }

    public function testDropColumnIsNoOpWhenColumnAbsent(): void
    {
        $this->adapter->createTable($this->descriptor());

        // No such column → early return, no exception, table untouched.
        $this->adapter->dropColumn('middag_widget', 'ghost');

        self::assertTrue($this->adapter->columnExists('middag_widget', 'title'));
    }

    public function testDropIndexIsNoOpWhenIndexAbsent(): void
    {
        $this->adapter->createTable($this->descriptor());

        // No such index → early return, no exception.
        $this->adapter->dropIndex('middag_widget', 'idx_missing');

        self::assertTrue(
            $this->connection->createSchemaManager()->introspectTable('middag_widget')->hasIndex('middag_widget_idx_widget_title'),
        );
    }

    public function testCreateTableEmitsAUniqueIndex(): void
    {
        $this->adapter->createTable([
            'name' => 'uqdemo',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'slug', 'type' => 'varchar', 'length' => 40],
            ],
            'indexes' => [
                ['name' => 'slug', 'columns' => ['slug'], 'unique' => true],
            ],
        ]);

        $index = $this->connection->createSchemaManager()->introspectTable('uqdemo')->getIndex('uqdemo_slug');
        self::assertTrue($index->isUnique());
    }

    public function testPrimaryKeyColumnsSkipsNonArrayAndNonPrimaryKeyEntries(): void
    {
        $this->adapter->createTable([
            'name' => 'nokey',
            'columns' => [
                ['name' => 'a', 'type' => 'bigint', 'notnull' => true],
                ['name' => 'b', 'type' => 'bigint', 'notnull' => true],
            ],
            'keys' => [
                'not-an-array',
                ['type' => 'foreign', 'fields' => ['a'], 'reftable' => 'other'],
            ],
        ]);

        // No 'primary' entry + no sequence column → the table has no primary key.
        $pk = [];
        foreach ($this->connection->fetchAllAssociative('PRAGMA table_info(nokey)') as $row) {
            if ((int) $row['pk'] > 0) {
                $pk[] = (string) $row['name'];
            }
        }

        self::assertSame([], $pk);
    }

    /**
     * @return array<string, mixed>
     */
    private function descriptor(): array
    {
        return [
            'name' => 'middag_widget',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'title', 'type' => 'varchar', 'length' => 120, 'notnull' => true],
                ['name' => 'active', 'type' => 'boolean', 'default' => 0],
            ],
            'indexes' => [
                ['name' => 'idx_widget_title', 'fields' => ['title']],
            ],
        ];
    }
}
