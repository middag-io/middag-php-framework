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

use Middag\Framework\Kernel\Contract\HookManagerInterface;
use Middag\Framework\Kernel\Facade\AbstractFacade;
use Middag\Framework\Kernel\Facade\HookFacade;
use Middag\Framework\Kernel\Manager\HookManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

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
}
