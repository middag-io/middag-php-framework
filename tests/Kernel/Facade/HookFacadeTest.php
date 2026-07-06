<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Facade;

use BadMethodCallException;
use Middag\Framework\Kernel\Contract\HookManagerInterface;
use Middag\Framework\Kernel\Facade\AbstractFacade;
use Middag\Framework\Kernel\Facade\HookFacade;
use Middag\Framework\Kernel\Manager\HookManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(HookFacade::class)]
#[CoversClass(AbstractFacade::class)]
final class HookFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        AbstractFacade::reset();
    }

    protected function tearDown(): void
    {
        AbstractFacade::reset();
    }

    public function testStaticCallsForwardToTheContainerResolvedInstance(): void
    {
        $hooks = new HookManager();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(HookManagerInterface::class)->willReturn($hooks);
        AbstractFacade::setFacadeContainer($container);

        HookFacade::addFilter('price', static fn (int $v): int => $v * 3);

        // Same underlying instance the container handed back.
        self::assertSame(30, HookFacade::applyFilters('price', 10));
        self::assertSame(30, $hooks->applyFilters('price', 10));
    }

    public function testSwapReplacesTheResolvedInstanceForTesting(): void
    {
        $fake = new HookManager();
        $fake->addAction('boot', static fn (): null => null);

        HookFacade::swap($fake);

        self::assertTrue(HookFacade::hasAction('boot'));
    }

    public function testAccessorPointsAtTheHookManagerContract(): void
    {
        self::assertSame(HookManagerInterface::class, HookFacade::getFacadeAccessor());
    }

    public function testUnknownMethodThrowsBadMethodCall(): void
    {
        HookFacade::swap(new HookManager());

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('does not exist');
        HookFacade::thisMethodDoesNotExistAnywhere(); // @phpstan-ignore-line — intentional missing method
    }

    public function testResolutionWithoutAContainerThrows(): void
    {
        // setUp() reset() cleared the container and no instance was swapped.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Facade container not set');
        HookFacade::getFacadeRoot();
    }

    public function testNonObjectResolutionThrows(): void
    {
        AbstractFacade::setFacadeContainer($this->countingContainer(static fn (): string => 'not-an-object'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not resolve to an object');
        HookFacade::getFacadeRoot();
    }

    public function testCachingResolvesOnceThenReusesTheInstance(): void
    {
        $container = $this->countingContainer(static fn (): HookManager => new HookManager());
        AbstractFacade::setFacadeContainer($container);

        self::assertSame(HookFacade::getFacadeRoot(), HookFacade::getFacadeRoot());
        self::assertSame(1, $container->gets, 'cached facade resolves the container exactly once');
    }

    public function testDisableCacheResolvesFreshEveryCall(): void
    {
        $container = $this->countingContainer(static fn (): HookManager => new HookManager());
        AbstractFacade::setFacadeContainer($container);
        AbstractFacade::disableCache();

        self::assertNotSame(HookFacade::getFacadeRoot(), HookFacade::getFacadeRoot());
        self::assertSame(2, $container->gets, 'cache-off resolves the container on every call');
    }

    public function testEnableCacheRestoresMemoisation(): void
    {
        $container = $this->countingContainer(static fn (): HookManager => new HookManager());
        AbstractFacade::setFacadeContainer($container);
        AbstractFacade::disableCache();
        AbstractFacade::enableCache();

        self::assertSame(HookFacade::getFacadeRoot(), HookFacade::getFacadeRoot());
        self::assertSame(1, $container->gets);
    }

    public function testClearResolvedInstanceForcesReResolution(): void
    {
        $container = $this->countingContainer(static fn (): HookManager => new HookManager());
        AbstractFacade::setFacadeContainer($container);

        HookFacade::getFacadeRoot();
        HookFacade::clearResolvedInstance();
        HookFacade::getFacadeRoot();

        self::assertSame(2, $container->gets);
    }

    public function testClearResolvedInstancesForcesReResolution(): void
    {
        $container = $this->countingContainer(static fn (): HookManager => new HookManager());
        AbstractFacade::setFacadeContainer($container);

        HookFacade::getFacadeRoot();
        AbstractFacade::clearResolvedInstances();
        HookFacade::getFacadeRoot();

        self::assertSame(2, $container->gets);
    }

    /**
     * A container whose get() runs $factory and counts the calls.
     *
     * @param callable(string): mixed $factory
     *
     * @return ContainerInterface&object{gets: int}
     */
    private function countingContainer(callable $factory): ContainerInterface
    {
        return new class($factory) implements ContainerInterface {
            public int $gets = 0;

            /** @param callable(string): mixed $factory */
            public function __construct(private $factory) {}

            public function get(string $id): mixed
            {
                ++$this->gets;

                return ($this->factory)($id);
            }

            public function has(string $id): bool
            {
                return true;
            }
        };
    }
}
