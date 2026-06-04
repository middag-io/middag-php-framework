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

use Middag\Framework\Persistence\Query\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Page::class)]
final class PageTest extends TestCase
{
    public function testNonPaginatedExposesItemsAndDefaults(): void
    {
        $page = new Page(['a', 'b', 'c'], 3);

        self::assertSame(['a', 'b', 'c'], $page->items());
        self::assertSame(3, $page->total());
        self::assertFalse($page->isPaginated());
        self::assertNull($page->page());
        self::assertNull($page->perpage());
        self::assertSame(1, $page->pages());
        self::assertFalse($page->isStdClass());
        self::assertSame(3, $page->count());
        self::assertCount(3, $page);
    }

    public function testPaginatedComputesPages(): void
    {
        $page = new Page(['a', 'b'], 10, 2, 4);

        self::assertTrue($page->isPaginated());
        self::assertSame(2, $page->page());
        self::assertSame(4, $page->perpage());
        self::assertSame(3, $page->pages());
        self::assertSame(2, $page->currentPage());
        self::assertSame(3, $page->lastPage());
    }

    public function testFirstAndLast(): void
    {
        $page = new Page(['x', 'y', 'z'], 3);

        self::assertSame('x', $page->first());
        self::assertSame('z', $page->last());
    }

    public function testEmptyNonPaginated(): void
    {
        $page = new Page([], 0);

        self::assertTrue($page->isEmpty());
        self::assertFalse($page->isNotEmpty());
        self::assertNull($page->first());
        self::assertNull($page->last());
        self::assertSame(1, $page->pages());
    }

    public function testEmptyPaginatedStillAtLeastOnePage(): void
    {
        $page = new Page([], 0, 1, 10);

        self::assertTrue($page->isPaginated());
        self::assertSame(1, $page->pages());
        self::assertSame(1, $page->lastPage());
    }

    public function testIteration(): void
    {
        $page = new Page(['a', 'b'], 2);

        $collected = [];
        foreach ($page as $item) {
            $collected[] = $item;
        }

        self::assertSame(['a', 'b'], $collected);
    }

    public function testStdClassFlag(): void
    {
        $page = new Page([], 0, null, null, true);

        self::assertTrue($page->isStdClass());
    }

    public function testToArrayReturnsItems(): void
    {
        $page = new Page(['a'], 1);

        self::assertSame(['a'], $page->toArray());
    }

    public function testIsNotEmpty(): void
    {
        $page = new Page(['a'], 1);

        self::assertTrue($page->isNotEmpty());
        self::assertFalse($page->isEmpty());
    }
}
