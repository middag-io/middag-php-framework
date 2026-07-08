<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Dto;

use Middag\Framework\Shared\Dto\SyncResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncResult::class)]
final class SyncResultTest extends TestCase
{
    #[Test]
    public function exposesCountsAndDefaultsErrorsToEmptyArray(): void
    {
        $result = new SyncResult(successCount: 5, failureCount: 0);

        self::assertSame(5, $result->successCount);
        self::assertSame(0, $result->failureCount);
        self::assertSame([], $result->errors);
    }

    #[Test]
    public function isFullSuccessWhenThereAreNoFailures(): void
    {
        $result = new SyncResult(successCount: 3, failureCount: 0);

        self::assertTrue($result->isFullSuccess());
    }

    #[Test]
    public function isNotFullSuccessWhenFailuresExist(): void
    {
        $result = new SyncResult(
            successCount: 2,
            failureCount: 1,
            errors: ['item 7 failed'],
        );

        self::assertFalse($result->isFullSuccess());
        self::assertSame(['item 7 failed'], $result->errors);
    }
}
