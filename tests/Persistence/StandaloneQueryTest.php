<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence;

use Middag\Framework\Database\PdoConnectionAdapter;
use Middag\Framework\Persistence\Query\QueryBuilder;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The standalone query stack (QueryBuilder ON-mode over
 * PdoConnectionAdapter + StandardSqlDialect) executes LIMIT/OFFSET against a
 * REAL engine, not just an asserted SQL string. Closes the comment-asserted-only
 * residual on limitOffset and proves the connection-bound
 * read/paginate path end to end with no host adapter.
 *
 * @internal
 */
#[CoversNothing]
final class StandaloneQueryTest extends TestCase
{
    private PdoConnectionAdapter $connection;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $pdo->exec("INSERT INTO items (name) VALUES ('a'), ('b'), ('c'), ('d'), ('e')");

        $this->connection = new PdoConnectionAdapter($pdo);
    }

    #[Test]
    public function limitOffsetSlicesRealEngineResults(): void
    {
        $rows = QueryBuilder::on($this->connection, 'items')
            ->orderBy('id')
            ->limit(2)
            ->offset(2)
            ->get();

        self::assertSame(['c', 'd'], array_column($rows, 'name'), 'LIMIT 2 OFFSET 2 returns the third and fourth rows');
    }

    #[Test]
    public function offsetOnlyUsesTheSqliteSentinelAndSkipsRows(): void
    {
        $rows = QueryBuilder::on($this->connection, 'items')
            ->orderBy('id')
            ->offset(3)
            ->get();

        self::assertSame(['d', 'e'], array_column($rows, 'name'), 'offset-only (LIMIT -1 OFFSET 3) skips the first three rows');
    }

    #[Test]
    public function paginateReportsTotalAndPageSlice(): void
    {
        $page = QueryBuilder::on($this->connection, 'items')->orderBy('id')->paginate(2, 2);

        self::assertSame(5, $page->total(), 'total ignores pagination');
        self::assertSame(3, $page->pages(), '5 rows / 2 per page = 3 pages');
        self::assertSame(['c', 'd'], array_column($page->items(), 'name'), 'page 2 holds the third and fourth rows');
    }
}
