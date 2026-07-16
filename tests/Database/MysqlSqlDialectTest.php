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

use Middag\Framework\Database\MysqlSqlDialect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MysqlSqlDialect::class)]
final class MysqlSqlDialectTest extends TestCase
{
    #[Test]
    public function limitOffsetEmitsBothBounds(): void
    {
        self::assertSame(' LIMIT 10 OFFSET 20', (new MysqlSqlDialect())->limitOffset(10, 20));
    }

    #[Test]
    public function limitOffsetEmitsLimitOnly(): void
    {
        self::assertSame(' LIMIT 10', (new MysqlSqlDialect())->limitOffset(10, null));
    }

    #[Test]
    public function limitOffsetEmitsMaxRowSentinelForOffsetOnly(): void
    {
        self::assertSame(
            ' LIMIT 18446744073709551615 OFFSET 5',
            (new MysqlSqlDialect())->limitOffset(null, 5),
        );
    }

    #[Test]
    public function limitOffsetTreatsZeroOffsetAsNoOffset(): void
    {
        self::assertSame(' LIMIT 10', (new MysqlSqlDialect())->limitOffset(10, 0));
    }

    #[Test]
    public function limitOffsetIsEmptyWithoutBounds(): void
    {
        self::assertSame('', (new MysqlSqlDialect())->limitOffset(null, null));
    }

    #[Test]
    public function tableAndCompareTextPassThrough(): void
    {
        $dialect = new MysqlSqlDialect();

        self::assertSame('middag_items', $dialect->table('middag_items'));
        self::assertSame('body', $dialect->compareText('body'));
    }

    #[Test]
    public function inClauseEmitsNamedPlaceholders(): void
    {
        [$sql, $params] = (new MysqlSqlDialect())->inClause([1, 2]);

        self::assertSame('IN (:p0, :p1)', $sql);
        self::assertSame(['p0' => 1, 'p1' => 2], $params);
    }

    #[Test]
    public function inClauseMatchesNothingWhenEmpty(): void
    {
        [$sql, $params] = (new MysqlSqlDialect())->inClause([]);

        self::assertSame('IN (NULL) AND 1 = 0', $sql);
        self::assertSame([], $params);
    }

    #[Test]
    public function lockClauseEmitsForUpdate(): void
    {
        self::assertSame(' FOR UPDATE', (new MysqlSqlDialect())->lockClause('update'));
    }

    #[Test]
    public function lockClauseEmitsForShare(): void
    {
        self::assertSame(' FOR SHARE', (new MysqlSqlDialect())->lockClause('share'));
    }

    #[Test]
    public function lockClauseDefaultsToForUpdateForUnknownMode(): void
    {
        self::assertSame(' FOR UPDATE', (new MysqlSqlDialect())->lockClause('nope'));
    }

    #[Test]
    public function upsertClauseEmitsOnDuplicateKeyUpdate(): void
    {
        self::assertSame(
            ' ON DUPLICATE KEY UPDATE name = VALUES(name), age = VALUES(age)',
            (new MysqlSqlDialect())->upsertClause(['id'], ['name', 'age']),
        );
    }

    #[Test]
    public function upsertClauseNoOpsThePkColumnWhenNoUpdateColumns(): void
    {
        self::assertSame(
            ' ON DUPLICATE KEY UPDATE email = email',
            (new MysqlSqlDialect())->upsertClause(['email'], []),
        );
    }

    #[Test]
    public function upsertClauseIsEmptyWhenNoConflictTargetAndNoUpdate(): void
    {
        self::assertSame('', (new MysqlSqlDialect())->upsertClause([], []));
    }
}
