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

use Middag\Framework\Database\Enum\Capability;
use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Database\StandardSqlDialect;
use Middag\Framework\Exception\MiddagPersistenceException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(PdoConnectionAdapter::class)]
final class PdoConnectionAdapterTest extends TestCase
{
    // ---- capabilities & dialect -------------------------------------------

    #[Test]
    public function supportsReportsAnsiBaselineOnly(): void
    {
        $adapter = $this->seeded();

        self::assertTrue($adapter->supports(Capability::Transactions));
        self::assertTrue($adapter->supports(Capability::Streaming));
        self::assertFalse($adapter->supports(Capability::JsonWhere));
        self::assertFalse($adapter->supports(Capability::Returning));
        self::assertFalse($adapter->supports(Capability::Upsert));
        self::assertFalse($adapter->supports(Capability::SchemaDiff));
        self::assertFalse($adapter->supports(Capability::RowLock));
    }

    #[Test]
    public function dialectDefaultsToStandardAndHonoursInjection(): void
    {
        self::assertInstanceOf(StandardSqlDialect::class, $this->seeded()->dialect());

        $dialect = new StandardSqlDialect();
        $adapter = new PdoConnectionAdapter($this->pdo(), $dialect);
        self::assertSame($dialect, $adapter->dialect());
    }

    // ---- execute -----------------------------------------------------------

    #[Test]
    public function executeReturnsAffectedRowCount(): void
    {
        $adapter = $this->seeded();

        self::assertSame(2, $adapter->execute('UPDATE t SET name = :n', ['n' => 'x']));
        self::assertSame(1, $adapter->execute('DELETE FROM t WHERE id = :id', ['id' => 1]));
    }

    #[Test]
    public function executeWrapsPdoException(): void
    {
        $adapter = $this->seeded();

        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('SQL execute failed');
        $adapter->execute('UPDATE no_such_table SET x = 1');
    }

    // ---- fetch / fetchAll --------------------------------------------------

    #[Test]
    public function fetchReturnsRowOrNull(): void
    {
        $adapter = $this->seeded();

        $row = $adapter->fetch('SELECT * FROM t WHERE id = :id', ['id' => 1]);
        self::assertSame('a', $row['name']);

        self::assertNull($adapter->fetch('SELECT * FROM t WHERE id = :id', ['id' => 999]));
    }

    #[Test]
    public function fetchWrapsPdoException(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('SQL fetch failed');
        $this->seeded()->fetch('SELECT * FROM no_such_table');
    }

    #[Test]
    public function fetchAllReturnsAllRows(): void
    {
        self::assertCount(2, $this->seeded()->fetchAll('SELECT * FROM t'));
    }

    #[Test]
    public function fetchAllWrapsPdoException(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('SQL fetchAll failed');
        $this->seeded()->fetchAll('SELECT * FROM no_such_table');
    }

    // ---- insert ------------------------------------------------------------

    #[Test]
    public function insertReturnsLastInsertIdAndPersistsRow(): void
    {
        $adapter = $this->seeded();

        $id = $adapter->insert('t', ['name' => 'c']);

        self::assertSame(3, $id);
        self::assertSame('c', $adapter->find('t', ['id' => 3])['name']);
    }

    #[Test]
    public function insertEmptyRecordThrows(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('empty record');
        $this->seeded()->insert('t', []);
    }

    #[Test]
    public function insertWrapsPdoException(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('SQL insert failed');
        $this->seeded()->insert('t', ['no_such_column' => 'x']);
    }

    // ---- update ------------------------------------------------------------

    #[Test]
    public function updateModifiesMatchingRow(): void
    {
        $adapter = $this->seeded();

        $adapter->update('t', ['id' => 1, 'name' => 'renamed']);

        self::assertSame('renamed', $adapter->find('t', ['id' => 1])['name']);
    }

    #[Test]
    public function updateWithoutIdThrows(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('has no id');
        $this->seeded()->update('t', ['name' => 'x']);
    }

    #[Test]
    public function updateWithOnlyIdIsNoOp(): void
    {
        $adapter = $this->seeded();

        // Only the id remains after it is stripped → nothing to set, early return.
        $adapter->update('t', ['id' => 1]);

        self::assertSame('a', $adapter->find('t', ['id' => 1])['name']);
    }

    #[Test]
    public function updateWrapsPdoException(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('SQL update failed');
        $this->seeded()->update('t', ['id' => 1, 'no_such_column' => 'x']);
    }

    // ---- delete ------------------------------------------------------------

    #[Test]
    public function deleteWithEmptyConditionsThrowsAndLeavesRowsIntact(): void
    {
        $adapter = $this->seeded();

        try {
            $adapter->delete('t', []);
            self::fail('Expected MiddagPersistenceException');
        } catch (MiddagPersistenceException $middagPersistenceException) {
            self::assertStringContainsString('refusing to delete all rows', $middagPersistenceException->getMessage());
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

    #[Test]
    public function deleteWrapsPdoException(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('SQL delete failed');
        $this->seeded()->delete('no_such_table', ['id' => 1]);
    }

    // ---- find / findAll ----------------------------------------------------

    #[Test]
    public function findReturnsMatchingRowOrNull(): void
    {
        $adapter = $this->seeded();

        self::assertSame('b', $adapter->find('t', ['id' => 2])['name']);
        self::assertNull($adapter->find('t', ['id' => 42]));
    }

    #[Test]
    public function findAllHonoursConditionsAndDefaultsToEveryRow(): void
    {
        $adapter = $this->seeded();

        self::assertCount(2, $adapter->findAll('t'));
        self::assertCount(1, $adapter->findAll('t', ['name' => 'a']));
    }

    // ---- transaction -------------------------------------------------------

    #[Test]
    public function transactionCommitsWorkAndReturnsItsValue(): void
    {
        $adapter = $this->seeded();

        $result = $adapter->transaction(static function (PdoConnectionAdapter $c): string {
            $c->insert('t', ['name' => 'tx']);

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertCount(3, $adapter->findAll('t'));
    }

    #[Test]
    public function transactionRollsBackAndRethrowsOnFailure(): void
    {
        $adapter = $this->seeded();

        try {
            $adapter->transaction(static function (PdoConnectionAdapter $c): never {
                $c->insert('t', ['name' => 'doomed']);

                throw new RuntimeException('boom');
            });
            self::fail('Expected the work exception to propagate');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('boom', $runtimeException->getMessage());
        }

        // The rolled-back insert must not survive.
        self::assertCount(2, $adapter->findAll('t'));
    }

    // ---- cursor ------------------------------------------------------------

    #[Test]
    public function cursorStreamsRowsLazily(): void
    {
        $adapter = $this->seeded();

        $names = [];
        foreach ($adapter->cursor('SELECT * FROM t ORDER BY id') as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(['a', 'b'], $names);
    }

    #[Test]
    public function cursorWrapsPdoException(): void
    {
        $this->expectException(MiddagPersistenceException::class);
        $this->expectExceptionMessage('SQL cursor failed');
        // The prepare/execute happens eagerly, so the throw surfaces here.
        $this->seeded()->cursor('SELECT * FROM no_such_table');
    }

    // ---- typed parameter binding ------------------------------------------

    #[Test]
    public function bindsPositionalIntegerParamsWithNumericAffinity(): void
    {
        $adapter = $this->seeded();

        // Positional (int-keyed) param bound 1-based as PARAM_INT — the numeric
        // comparison must not be coerced to a string.
        $rows = $adapter->fetchAll('SELECT * FROM t WHERE id >= ?', [2]);

        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['id']);
    }

    #[Test]
    public function bindsBooleanParam(): void
    {
        $adapter = $this->seeded();

        // true binds as PARAM_BOOL → 1, matching row id = 1.
        $row = $adapter->fetch('SELECT * FROM t WHERE id = ?', [true]);

        self::assertSame('a', $row['name']);
    }

    #[Test]
    public function bindsNullParam(): void
    {
        $adapter = $this->seeded();

        $adapter->insert('t', ['name' => null]);

        $row = $adapter->fetch('SELECT * FROM t WHERE name IS NULL');
        self::assertNotNull($row);
        self::assertNull($row['name']);
    }

    // ---- fixtures ----------------------------------------------------------

    private function seeded(): PdoConnectionAdapter
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO t (id, name) VALUES (1, 'a'), (2, 'b')");

        return new PdoConnectionAdapter($pdo);
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
