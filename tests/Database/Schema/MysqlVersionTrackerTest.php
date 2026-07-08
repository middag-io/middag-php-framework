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
use Middag\Framework\Database\Schema\MysqlVersionTracker;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Despite the name, the tracker emits plain ANSI SQL over the connection
 * contract and is portable to SQLite — so an in-memory PDO connection
 * exercises the real DML/DDL paths.
 *
 * @internal
 */
#[CoversClass(MysqlVersionTracker::class)]
final class MysqlVersionTrackerTest extends TestCase
{
    public function testConstructionAutoCreatesTheSharedVersionsTable(): void
    {
        $pdo = $this->pdo();
        new MysqlVersionTracker(new PdoConnectionAdapter($pdo), 'lib_a');

        $name = $pdo
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '_middag_schema_versions'")
            ->fetchColumn();

        self::assertSame('_middag_schema_versions', $name);
    }

    public function testGetVersionReturnsZeroWhenNoRowExists(): void
    {
        $tracker = new MysqlVersionTracker(new PdoConnectionAdapter($this->pdo()), 'lib_a');

        self::assertSame(0, $tracker->getVersion());
    }

    public function testSetVersionInsertsThenGetVersionReadsItBack(): void
    {
        $tracker = new MysqlVersionTracker(new PdoConnectionAdapter($this->pdo()), 'lib_a');

        $tracker->setVersion(42);

        self::assertSame(42, $tracker->getVersion());
    }

    public function testSetVersionUpdatesExistingRowInPlace(): void
    {
        $pdo = $this->pdo();
        $tracker = new MysqlVersionTracker(new PdoConnectionAdapter($pdo), 'lib_a');

        $tracker->setVersion(5);
        $tracker->setVersion(8);

        self::assertSame(8, $tracker->getVersion());
        // The upsert must UPDATE, never accumulate duplicate rows for the key.
        $count = (int) $pdo
            ->query("SELECT COUNT(*) FROM _middag_schema_versions WHERE lib_key = 'lib_a'")
            ->fetchColumn();
        self::assertSame(1, $count);
    }

    public function testLibKeyNamespacesVersionsIndependentlyInTheSharedTable(): void
    {
        $pdo = $this->pdo();
        $connection = new PdoConnectionAdapter($pdo);

        // Two libs share the same table but keep their own version rows.
        $trackerA = new MysqlVersionTracker($connection, 'lib_a');
        $trackerB = new MysqlVersionTracker($connection, 'lib_b');

        $trackerA->setVersion(3);
        $trackerB->setVersion(9);

        self::assertSame(3, $trackerA->getVersion());
        self::assertSame(9, $trackerB->getVersion());

        // ensureTable() (CREATE TABLE IF NOT EXISTS) ran twice without error.
        $rows = (int) $pdo->query('SELECT COUNT(*) FROM _middag_schema_versions')->fetchColumn();
        self::assertSame(2, $rows);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
