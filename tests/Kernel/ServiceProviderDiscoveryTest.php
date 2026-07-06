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

use Middag\Framework\Kernel\ServiceProvider;
use Middag\Framework\Tests\Fixture\Discovery\DiscoveryServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drives the convention-based auto-discovery of {@see ServiceProvider} against a
 * fixture src tree: scanDirectory / fileToClassName / shouldRegister /
 * registerInterfaceAliases, including every skip branch and the #[Lazy] flag.
 *
 * @internal
 */
#[CoversClass(ServiceProvider::class)]
final class ServiceProviderDiscoveryTest extends TestCase
{
    private const NS = 'Middag\Framework\Tests\Fixture\Discovery\src';

    public function testRegistersAndAliasesConventionServices(): void
    {
        $container = $this->discover();

        // `Service` suffix, concrete, public ctor → registered, not lazy.
        self::assertTrue($container->hasDefinition(self::NS . '\WidgetService'));
        self::assertFalse($container->getDefinition(self::NS . '\WidgetService')->isLazy());

        // Implements exactly one project interface → aliased to the concrete.
        self::assertTrue($container->hasAlias(self::NS . '\WidgetInterface'));
        self::assertSame(
            self::NS . '\WidgetService',
            (string) $container->getAlias(self::NS . '\WidgetInterface'),
        );
    }

    public function testLazyAttributeFlagsTheDefinition(): void
    {
        $container = $this->discover();

        self::assertTrue($container->hasDefinition(self::NS . '\LazyReportService'));
        self::assertTrue($container->getDefinition(self::NS . '\LazyReportService')->isLazy());
    }

    public function testSkipsEveryNonRegistrableClass(): void
    {
        $container = $this->discover();

        // No register suffix.
        self::assertFalse($container->hasDefinition(self::NS . '\PlainThing'));
        // `Dto` ignore-suffix wins over register match.
        self::assertFalse($container->hasDefinition(self::NS . '\WidgetDto'));
        // Abstract, though `Service`-suffixed.
        self::assertFalse($container->hasDefinition(self::NS . '\AbstractBaseService'));
        // Non-public constructor (static factory).
        self::assertFalse($container->hasDefinition(self::NS . '\FactoryOnlyService'));
        // Under an IGNORE_DIRS directory ("Contract").
        self::assertFalse($container->hasDefinition(self::NS . '\Contract\IgnoredService'));
    }

    private function discover(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        DiscoveryServiceProvider::register($container, dirname(__DIR__) . '/Fixture/Discovery');

        return $container;
    }
}
