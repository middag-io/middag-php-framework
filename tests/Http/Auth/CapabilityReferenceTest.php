<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Auth;

use Middag\Framework\Http\Auth\CapabilityReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CapabilityReference::class)]
final class CapabilityReferenceTest extends TestCase
{
    #[Test]
    public function keyOnlyLeavesHostNull(): void
    {
        $reference = new CapabilityReference('moodle/course:view');

        $this->assertSame('moodle/course:view', $reference->key);
        $this->assertNull($reference->host);
    }

    #[Test]
    public function capturesTheHostWhenProvided(): void
    {
        $reference = new CapabilityReference('mod/quiz:attempt', host: 'moodle');

        $this->assertSame('mod/quiz:attempt', $reference->key);
        $this->assertSame('moodle', $reference->host);
    }
}
