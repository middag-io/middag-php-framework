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

use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Framework\Database\PrefixingSqlDialect;
use Middag\Framework\Database\StandardSqlDialect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PrefixingSqlDialect::class)]
final class PrefixingSqlDialectTest extends TestCase
{
    #[Test]
    public function tablePrependsPrefixOverStandardDialect(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'meusite_');

        self::assertSame('meusite_middag_items', $dialect->table('middag_items'));
    }

    #[Test]
    public function tablePrefixesBeforeDelegatingToInner(): void
    {
        // The prefix must be applied first: the inner dialect sees (and renders)
        // the already-prefixed name. Proven with an inner that braces the name.
        $inner = $this->createMock(SqlDialectInterface::class);
        $inner->expects(self::once())
            ->method('table')
            ->with('wp_items')
            ->willReturn('{wp_items}');

        $dialect = new PrefixingSqlDialect($inner, 'wp_');

        self::assertSame('{wp_items}', $dialect->table('items'));
    }

    #[Test]
    public function emptyPrefixLeavesNameUnchanged(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), '');

        self::assertSame('items', $dialect->table('items'));
    }

    #[Test]
    public function delegatesInClauseToInner(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'x_');

        [$sql, $params] = $dialect->inClause([1, 2]);

        self::assertSame('IN (:p0, :p1)', $sql);
        self::assertSame(['p0' => 1, 'p1' => 2], $params);
    }

    #[Test]
    public function delegatesCompareTextToInner(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'x_');

        self::assertSame('body', $dialect->compareText('body'));
    }

    #[Test]
    public function delegatesLimitOffsetToInner(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'x_');

        self::assertSame(' LIMIT 10 OFFSET 20', $dialect->limitOffset(10, 20));
    }

    #[Test]
    public function delegatesLockClauseToInner(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'x_');

        self::assertSame(' FOR UPDATE', $dialect->lockClause('update'));
    }

    #[Test]
    public function delegatesUpsertClauseToInner(): void
    {
        $dialect = new PrefixingSqlDialect(new StandardSqlDialect(), 'x_');

        self::assertSame(
            ' ON CONFLICT (id) DO UPDATE SET name = excluded.name',
            $dialect->upsertClause(['id'], ['name']),
        );
    }
}
