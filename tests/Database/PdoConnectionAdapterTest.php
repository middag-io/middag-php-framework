<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Database;

use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Exception\MiddagPersistenceException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PdoConnectionAdapter::class)]
final class PdoConnectionAdapterTest extends TestCase
{
    #[Test]
    public function deleteWithEmptyConditionsThrowsInsteadOfWipingTable(): void
    {
        $adapter = $this->seeded();

        $this->expectException(MiddagPersistenceException::class);
        $adapter->delete('t', []);
    }

    #[Test]
    public function deleteWithEmptyConditionsLeavesRowsIntact(): void
    {
        $adapter = $this->seeded();

        try {
            $adapter->delete('t', []);
        } catch (MiddagPersistenceException) {
            // Expected — assert the guard did not delete anything.
        }

        self::assertCount(2, $adapter->findAll('t'));
    }

    #[Test]
    public function deleteWithConditionsRemovesOnlyMatchingRows(): void
    {
        $adapter = $this->seeded();

        $adapter->delete('t', ['id' => 1]);

        $rows = $adapter->findAll('t');
        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['id']);
    }

    private function seeded(): PdoConnectionAdapter
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO t (id, name) VALUES (1, 'a'), (2, 'b')");

        return new PdoConnectionAdapter($pdo);
    }
}
