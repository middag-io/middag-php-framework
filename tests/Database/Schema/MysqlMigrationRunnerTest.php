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
use Middag\Framework\Database\Schema\MigrationRunner;
use Middag\Framework\Database\Schema\MysqlMigrationRunner;
use Middag\Framework\Database\Schema\MysqlSchemaBuilderAdapter;
use Middag\Framework\Database\Schema\MysqlVersionTracker;
use Middag\Framework\Database\Schema\SchemaBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The factory has no MySQL server available, so wiring is asserted structurally:
 * make() must return a MigrationRunner backed by the MySQL adapter + tracker.
 * The tracker's constructor runs its ensure-table DDL against the injected
 * connection, which the capturing stub records.
 *
 * @internal
 */
#[CoversClass(MysqlMigrationRunner::class)]
final class MysqlMigrationRunnerTest extends TestCase
{
    public function testMakeWiresTheMysqlAdapterAndVersionTracker(): void
    {
        $connection = $this->capturingConnection();

        $runner = MysqlMigrationRunner::make(new SchemaBuilder(), $connection, 'my_lib');

        self::assertInstanceOf(MysqlMigrationRunner::class, $runner);
        self::assertInstanceOf(MigrationRunner::class, $runner);

        self::assertInstanceOf(
            MysqlSchemaBuilderAdapter::class,
            (new ReflectionProperty(MigrationRunner::class, 'adapter'))->getValue($runner),
        );
        self::assertInstanceOf(
            MysqlVersionTracker::class,
            (new ReflectionProperty(MigrationRunner::class, 'tracker'))->getValue($runner),
        );

        // Constructing the tracker auto-creates the shared versions table.
        self::assertNotSame([], $connection->queries);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS', $connection->queries[0]);
    }

    public function testMakeDefaultsTheLibKeyWhenNoneIsGiven(): void
    {
        $runner = MysqlMigrationRunner::make(new SchemaBuilder(), $this->capturingConnection());

        self::assertInstanceOf(MysqlMigrationRunner::class, $runner);
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
