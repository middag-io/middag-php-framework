<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Persistence\Query;

use InvalidArgumentException;
use LogicException;
use Middag\Framework\Persistence\Query\QueryBuilder;
use Middag\Framework\Tests\Persistence\Query\Fixture\StubConnectionAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Edge branches of the QueryBuilder not exercised by QueryBuilderTest:
 * argument-guard throws, the cursor no-streaming path, and the numeric
 * aggregate string-coercion branches (driven by a stubbed connection whose
 * fetch() returns numeric strings the way non-typed drivers do).
 *
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderCoverageTest extends TestCase
{
    #[Test]
    public function whereWithOnlyAColumnThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('users')->where('id');
    }

    #[Test]
    public function whereColumnWithExplicitNullSecondThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Three args reach the resolve branch, but a null second column is rejected.
        QueryBuilder::for('users')->whereColumn('a', '=', null);
    }

    #[Test]
    public function havingWithOnlyAColumnThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('orders')->having('total');
    }

    #[Test]
    public function orHavingWithOnlyAColumnThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryBuilder::for('orders')->orHaving('total');
    }

    #[Test]
    public function cursorThrowsWhenConnectionDoesNotSupportStreaming(): void
    {
        $builder = QueryBuilder::on(new StubConnectionAdapter(supportsStreaming: false), 'users');

        $this->expectException(LogicException::class);
        // iterate to force the generator/return path
        foreach ($builder->cursor() as $ignored) {
            // unreachable
        }
    }

    #[Test]
    public function numericAggregateCoercesDecimalStringToFloat(): void
    {
        $builder = QueryBuilder::on(new StubConnectionAdapter(['aggregate' => '12.5']), 'sales');

        self::assertSame(12.5, $builder->sum('amount'));
    }

    #[Test]
    public function numericAggregateCoercesExponentStringToFloat(): void
    {
        $builder = QueryBuilder::on(new StubConnectionAdapter(['aggregate' => '1e3']), 'sales');

        self::assertSame(1000.0, $builder->avg('amount'));
    }

    #[Test]
    public function numericAggregateCoercesPlainStringToInt(): void
    {
        $builder = QueryBuilder::on(new StubConnectionAdapter(['aggregate' => '42']), 'sales');

        self::assertSame(42, $builder->avg('amount'));
    }

    #[Test]
    public function numericAggregateReturnsNullForNonNumericString(): void
    {
        $builder = QueryBuilder::on(new StubConnectionAdapter(['aggregate' => 'not-a-number']), 'sales');

        self::assertNull($builder->avg('amount'));
    }

    #[Test]
    public function sumFallsBackToZeroWhenAggregateIsNull(): void
    {
        $builder = QueryBuilder::on(new StubConnectionAdapter(['aggregate' => null]), 'sales');

        self::assertSame(0, $builder->sum('amount'));
    }

    #[Test]
    public function compilingANestedWhereWithACorruptedEntryThrows(): void
    {
        // whereNested() (reached through where(Closure)) already rejects any
        // closure that doesn't return the QueryBuilder it received, so a
        // 'query' entry that isn't a QueryBuilder can never arise through the
        // public API. compileNestedWhere()'s own instanceof guard is a second
        // line of defense against that same invariant — reachable only if the
        // internal $wheres state is corrupted directly, as done here via
        // reflection.
        $builder = QueryBuilder::for('users')->where(static fn (QueryBuilder $q): QueryBuilder => $q->where('active', true));

        $wheresProperty = new ReflectionProperty(QueryBuilder::class, 'wheres');
        $wheres = $wheresProperty->getValue($builder);
        $wheres[0]['query'] = 'not-a-query-builder';
        $wheresProperty->setValue($builder, $wheres);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nested where must hold a QueryBuilder.');
        $builder->compile();
    }
}
