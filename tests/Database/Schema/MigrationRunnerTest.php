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
use Middag\Framework\Database\Contract\SchemaBuilderAdapterInterface;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\Schema\MigrationRunner;
use Middag\Framework\Database\Schema\MysqlVersionTracker;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Middag\Framework\Database\Schema\SqliteSchemaBuilderAdapter;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The abstract runner is exercised through a minimal concrete subclass wired to
 * the SQLite adapter + the portable version tracker over an in-memory PDO, so
 * install()/upgrade() run real DDL instead of asserting against mocks.
 *
 * @internal
 */
#[CoversClass(MigrationRunner::class)]
final class MigrationRunnerTest extends TestCase
{
    public function testInstallCreatesEveryMissingTable(): void
    {
        $connection = $this->connection();
        $adapter = new SqliteSchemaBuilderAdapter($connection);
        $builder = (new SchemaBuilder())
            ->register($this->itemsDescriptor())
            ->register($this->tagsDescriptor());

        $this->runner($builder, $adapter, $connection)->install();

        self::assertTrue($adapter->tableExists('items'));
        self::assertTrue($adapter->tableExists('tags'));
    }

    public function testInstallIsIdempotentForAlreadyPresentTables(): void
    {
        $connection = $this->connection();
        $adapter = new SqliteSchemaBuilderAdapter($connection);
        $builder = (new SchemaBuilder())->register($this->itemsDescriptor());
        $runner = $this->runner($builder, $adapter, $connection);

        $runner->install();
        // Second pass: the table already exists, so createTable() is skipped.
        $runner->install();

        self::assertTrue($adapter->tableExists('items'));
    }

    public function testUpgradeCreatesAbsentTablesAndAddsMissingColumns(): void
    {
        $connection = $this->connection();
        $adapter = new SqliteSchemaBuilderAdapter($connection);

        // Pre-create 'items' with only the id column to simulate an older schema.
        $adapter->createTable([
            'name' => 'items',
            'columns' => [['name' => 'id', 'type' => 'bigint', 'sequence' => true]],
        ]);

        // The current descriptors add a 'name' column to items and a brand-new 'tags' table.
        $builder = (new SchemaBuilder())
            ->register($this->itemsDescriptor())
            ->register($this->tagsDescriptor());

        $this->runner($builder, $adapter, $connection)->upgrade(0);

        self::assertTrue($adapter->columnExists('items', 'name'), 'missing column back-filled');
        self::assertTrue($adapter->tableExists('tags'), 'absent table created during upgrade');
    }

    public function testGetAndSetInstalledVersionDelegateToTheTracker(): void
    {
        $connection = $this->connection();
        $runner = $this->runner(new SchemaBuilder(), new SqliteSchemaBuilderAdapter($connection), $connection);

        self::assertSame(0, $runner->getInstalledVersion());

        $runner->setInstalledVersion(7);

        self::assertSame(7, $runner->getInstalledVersion());
    }

    public function testUpgradeInvokesTheOnUpgradeHookWithThePreviousVersion(): void
    {
        $connection = $this->connection();
        $adapter = new SqliteSchemaBuilderAdapter($connection);
        $tracker = new MysqlVersionTracker($connection, 'hook');

        $runner = new class(new SchemaBuilder(), $adapter, $tracker) extends MigrationRunner {
            public ?int $seenOldVersion = null;

            protected function onUpgrade(int $oldVersion): void
            {
                $this->seenOldVersion = $oldVersion;
            }
        };

        $runner->upgrade(11);

        self::assertSame(11, $runner->seenOldVersion);
    }

    /**
     * A plain concrete runner that keeps the base (no-op) onUpgrade() hook.
     */
    private function runner(
        SchemaBuilder $builder,
        SchemaBuilderAdapterInterface $adapter,
        ConnectionInterface $connection,
    ): MigrationRunner {
        $tracker = new MysqlVersionTracker($connection, 'runner');

        return new class($builder, $adapter, $tracker) extends MigrationRunner {};
    }

    /**
     * A fresh in-memory SQLite connection shared between adapter and tracker.
     */
    private function connection(): ConnectionInterface
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return new PdoConnectionAdapter($pdo);
    }

    /** @return array<string, mixed> */
    private function itemsDescriptor(): array
    {
        return [
            'name' => 'items',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
                ['name' => 'name', 'type' => 'varchar', 'length' => 64],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function tagsDescriptor(): array
    {
        return [
            'name' => 'tags',
            'columns' => [
                ['name' => 'id', 'type' => 'bigint', 'sequence' => true],
            ],
        ];
    }
}
