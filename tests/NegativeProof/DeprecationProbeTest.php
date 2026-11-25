<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\NegativeProof;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Temporary probe: CI must fail on both deprecations.
 *
 * @internal
 */
#[CoversNothing]
final class DeprecationProbeTest extends TestCase
{
    public function testAnUnsuppressedDeprecationFailsTheRun(): void
    {
        trigger_error('negative proof', E_USER_DEPRECATED);

        self::assertTrue(true);
    }

    public function testASuppressedDeprecationFailsTheRun(): void
    {
        @trigger_error('suppressed proof', E_USER_DEPRECATED);

        self::assertTrue(true);
    }
}
