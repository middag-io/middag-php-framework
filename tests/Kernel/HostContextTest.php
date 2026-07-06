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
use ReflectionClass;

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

    #[Test]
    public function getReturnsContextWhoseBasePathIsNull(): void
    {
        $context = $this->fakeContextWithNullBasePath();

        HostContext::set($context);

        self::assertSame($context, HostContext::get());
        self::assertNull(HostContext::get()?->basePath());
        self::assertSame('example', HostContext::get()?->componentName());
        self::assertSame('1.2.3', HostContext::get()?->assetVersion());
    }

    #[Test]
    public function constructorIsPrivateToEnforceTheStaticRegistryPattern(): void
    {
        $reflection = new ReflectionClass(HostContext::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate(), 'HostContext is a static registry — no instances');

        // Invoke the (empty) private constructor via reflection purely to prove
        // it is inert; instances are never used at runtime.
        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        self::assertInstanceOf(HostContext::class, $instance);
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

    private function fakeContextWithNullBasePath(): HostComponentContextInterface
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

            public function basePath(): ?string
            {
                return null;
            }
        };
    }
}
