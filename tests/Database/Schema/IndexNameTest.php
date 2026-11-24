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

use Middag\Framework\Database\Schema\IndexName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IndexName::class)]
final class IndexNameTest extends TestCase
{
    public function testANameAtTheLimitIsKeptAsIs(): void
    {
        $name = str_repeat('a', 63);

        self::assertSame($name, IndexName::fit($name, IndexName::POSTGRESQL_MAX_BYTES));
    }

    public function testANameOverTheLimitIsShortenedWithAHashSuffix(): void
    {
        $name = str_repeat('a', 64);

        $fitted = IndexName::fit($name, IndexName::POSTGRESQL_MAX_BYTES);

        self::assertLessThanOrEqual(63, strlen($fitted));
        self::assertMatchesRegularExpression('/^a+_[0-9a-f]{8}$/', $fitted);
    }

    public function testShorteningIsDeterministic(): void
    {
        $name = str_repeat('table_', 12) . 'idx';

        self::assertSame(
            IndexName::fit($name, IndexName::POSTGRESQL_MAX_BYTES),
            IndexName::fit($name, IndexName::POSTGRESQL_MAX_BYTES),
        );
    }

    public function testNamesSharingTheFirst63BytesStayDistinct(): void
    {
        $prefix = str_repeat('p', 63);

        self::assertNotSame(
            IndexName::fit($prefix . '_one', IndexName::POSTGRESQL_MAX_BYTES),
            IndexName::fit($prefix . '_two', IndexName::POSTGRESQL_MAX_BYTES),
        );
    }

    public function testAMultibyteCharacterAtTheCutIsNotSplit(): void
    {
        // 53 ASCII bytes put the 2-byte "ç" across the 54-byte prefix cut.
        $name = str_repeat('a', 53) . str_repeat('ç', 10);

        $fitted = IndexName::fit($name, IndexName::POSTGRESQL_MAX_BYTES);

        self::assertLessThanOrEqual(63, strlen($fitted));
        self::assertTrue(mb_check_encoding($fitted, 'UTF-8'));
    }

    public function testTheMysqlLimitKeeps64ByteNames(): void
    {
        $name = str_repeat('m', 64);

        self::assertSame($name, IndexName::fit($name, IndexName::MYSQL_MAX_BYTES));
        self::assertLessThanOrEqual(64, strlen(IndexName::fit($name . 'm', IndexName::MYSQL_MAX_BYTES)));
    }

    public function testQualifiedPrefixesTheTableAndFits(): void
    {
        self::assertSame('orders_status', IndexName::qualified('orders', 'status', IndexName::POSTGRESQL_MAX_BYTES));
        self::assertLessThanOrEqual(
            63,
            strlen(IndexName::qualified(str_repeat('t', 40), str_repeat('i', 40), IndexName::POSTGRESQL_MAX_BYTES)),
        );
    }

    public function testDropCandidatesListTheFittedThenTheLegacyNames(): void
    {
        $table = str_repeat('t', 40);
        $index = str_repeat('i', 40);
        $qualified = $table . '_' . $index;

        self::assertSame(
            [
                IndexName::fit($qualified, IndexName::POSTGRESQL_MAX_BYTES),
                $qualified,
                substr($qualified, 0, 63),
                $index,
            ],
            IndexName::dropCandidates($table, $index, IndexName::POSTGRESQL_MAX_BYTES),
        );
    }

    public function testDropCandidatesForAShortNameHaveNoDuplicates(): void
    {
        self::assertSame(
            ['orders_status', 'status'],
            IndexName::dropCandidates('orders', 'status', IndexName::POSTGRESQL_MAX_BYTES),
        );
    }
}
