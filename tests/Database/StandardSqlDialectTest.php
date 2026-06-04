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

use Middag\Framework\Database\StandardSqlDialect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StandardSqlDialect::class)]
final class StandardSqlDialectTest extends TestCase
{
    #[Test]
    public function limitOffsetEmitsBothBounds(): void
    {
        self::assertSame(' LIMIT 10 OFFSET 20', (new StandardSqlDialect())->limitOffset(10, 20));
    }

    #[Test]
    public function limitOffsetEmitsLimitOnly(): void
    {
        self::assertSame(' LIMIT 10', (new StandardSqlDialect())->limitOffset(10, null));
    }

    #[Test]
    public function limitOffsetEmitsSqliteSentinelForOffsetOnly(): void
    {
        self::assertSame(' LIMIT -1 OFFSET 5', (new StandardSqlDialect())->limitOffset(null, 5));
    }

    #[Test]
    public function limitOffsetIsEmptyWithoutBounds(): void
    {
        self::assertSame('', (new StandardSqlDialect())->limitOffset(null, null));
    }

    #[Test]
    public function tableAndCompareTextPassThrough(): void
    {
        $dialect = new StandardSqlDialect();

        self::assertSame('middag_items', $dialect->table('middag_items'));
        self::assertSame('body', $dialect->compareText('body'));
    }

    #[Test]
    public function inClauseEmitsNamedPlaceholders(): void
    {
        [$sql, $params] = (new StandardSqlDialect())->inClause([1, 2]);

        self::assertSame('IN (:p0, :p1)', $sql);
        self::assertSame(['p0' => 1, 'p1' => 2], $params);
    }

    #[Test]
    public function inClauseMatchesNothingWhenEmpty(): void
    {
        [$sql, $params] = (new StandardSqlDialect())->inClause([]);

        self::assertSame('IN (NULL) AND 1 = 0', $sql);
        self::assertSame([], $params);
    }

    #[Test]
    public function lockClauseEmitsForUpdate(): void
    {
        self::assertSame(' FOR UPDATE', (new StandardSqlDialect())->lockClause('update'));
    }

    #[Test]
    public function lockClauseEmitsForShare(): void
    {
        self::assertSame(' FOR SHARE', (new StandardSqlDialect())->lockClause('share'));
    }

    #[Test]
    public function lockClauseIsEmptyForUnknownMode(): void
    {
        self::assertSame('', (new StandardSqlDialect())->lockClause('nope'));
    }

    #[Test]
    public function upsertClauseEmitsOnConflictDoUpdate(): void
    {
        self::assertSame(
            ' ON CONFLICT (id) DO UPDATE SET name = excluded.name, age = excluded.age',
            (new StandardSqlDialect())->upsertClause(['id'], ['name', 'age']),
        );
    }

    #[Test]
    public function upsertClauseEmitsDoNothingWithoutUpdateColumns(): void
    {
        self::assertSame(
            ' ON CONFLICT (email) DO NOTHING',
            (new StandardSqlDialect())->upsertClause(['email'], []),
        );
    }
}
