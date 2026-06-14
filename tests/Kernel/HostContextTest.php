<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel;

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Kernel\HostContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HostContext::class)]
final class HostContextTest extends TestCase
{
    protected function setUp(): void
    {
        HostContext::reset();
    }

    protected function tearDown(): void
    {
        HostContext::reset();
    }

    #[Test]
    public function getReturnsNullWhenNoHostConfigured(): void
    {
        self::assertNull(HostContext::get());
    }

    #[Test]
    public function setThenGetReturnsTheRegisteredContext(): void
    {
        $context = $this->fakeContext();

        HostContext::set($context);

        self::assertSame($context, HostContext::get());
    }

    #[Test]
    public function resetClearsTheRegisteredContext(): void
    {
        HostContext::set($this->fakeContext());

        HostContext::reset();

        self::assertNull(HostContext::get());
    }

    private function fakeContext(): HostComponentContextInterface
    {
        return new class implements HostComponentContextInterface {
            public function componentName(): string
            {
                return 'example';
            }

            public function assetVersion(): string
            {
                return '1.2.3';
            }

            public function basePath(): string
            {
                return '/srv/example';
            }
        };
    }
}
