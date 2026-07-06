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
