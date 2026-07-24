<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Routing;

use Middag\Framework\Http\Routing\RouteLoader;
use Middag\Framework\Tests\Http\Fixture\Discovery\AlphaController;
use Middag\Framework\Tests\Http\Fixture\Discovery\BetaController;
use Middag\Framework\Tests\Http\Fixture\Discovery\PlainService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollection;

/**
 * Opt-in route auto-discovery (issue #61): the discovery → RouteCollection
 * bridge. Deterministic order, class-level prefix, name collision, dedup across
 * directories, and the skip rules (no #[Route], abstract).
 *
 * @internal
 */
#[CoversClass(RouteLoader::class)]
final class RouteLoaderDiscoveryTest extends TestCase
{
    private const DISCOVERY_DIR = __DIR__ . '/../Fixture/Discovery';

    #[Test]
    public function discoversRoutesFromControllersWithoutAManualList(): void
    {
        $routes = new RouteCollection();
        (new RouteLoader())->discoverRoutes($routes, $this->container(), [self::DISCOVERY_DIR]);

        $this->assertNotNull($routes->get('alpha.index'), 'method-level route discovered');
        $this->assertSame('/alpha', $routes->get('alpha.index')?->getPath());

        // Class-level #[Route] contributes both path and name prefix.
        $this->assertNotNull($routes->get('beta.list'), 'class-prefixed route discovered');
        $this->assertSame('/beta/list', $routes->get('beta.list')?->getPath());
    }

    #[Test]
    public function registrationOrderIsDeterministicBySortedFqcn(): void
    {
        $routes = new RouteCollection();
        (new RouteLoader())->discoverRoutes($routes, $this->container(), [self::DISCOVERY_DIR]);

        // Alpha < Beta < Collision\One < Collision\Two by FQCN; the collided
        // `dup` name lands last after its final (re)insertion.
        $this->assertSame(
            ['alpha.index', 'beta.list', 'dup'],
            array_keys($routes->all()),
            'routes register in sorted-FQCN order regardless of filesystem read order',
        );
    }

    #[Test]
    public function nameCollisionResolvesDeterministicallyToTheLaterClass(): void
    {
        $routes = new RouteCollection();
        (new RouteLoader())->discoverRoutes($routes, $this->container(), [self::DISCOVERY_DIR]);

        // OneController and TwoController both declare name `dup`; TwoController
        // sorts later, so it is registered last and wins.
        $this->assertCount(3, $routes, 'the shared name collapses to a single route');
        $this->assertSame('/dup-two', $routes->get('dup')?->getPath());
    }

    #[Test]
    public function scanningTheSameDirectoryTwiceDeduplicatesByFqcn(): void
    {
        $once = new RouteCollection();
        (new RouteLoader())->discoverRoutes($once, $this->container(), [self::DISCOVERY_DIR]);

        $twice = new RouteCollection();
        (new RouteLoader())->discoverRoutes($twice, $this->container(), [self::DISCOVERY_DIR, self::DISCOVERY_DIR]);

        $this->assertSame(
            array_keys($once->all()),
            array_keys($twice->all()),
            'a controller reachable through two directories is loaded exactly once',
        );
    }

    #[Test]
    public function classesWithoutRoutesAndAbstractControllersAreSkipped(): void
    {
        $routes = new RouteCollection();
        (new RouteLoader())->discoverRoutes($routes, $this->container(), [self::DISCOVERY_DIR]);

        $this->assertNull($routes->get('abstract.index'), 'abstract controllers are skipped even when routed');
        foreach ($routes->all() as $route) {
            $this->assertNotSame([PlainService::class, 'doThing'], $route->getDefault('_controller'));
        }
    }

    #[Test]
    public function missingDirectoriesAreIgnored(): void
    {
        $routes = new RouteCollection();
        (new RouteLoader())->discoverRoutes($routes, $this->container(), [self::DISCOVERY_DIR . '/does-not-exist']);

        $this->assertCount(0, $routes, 'a non-existent directory contributes no routes and does not error');
    }

    #[Test]
    public function discoveredControllersAreRegisteredAsServicesAndPlainClassesAreNot(): void
    {
        $container = new ContainerBuilder();
        $routes = new RouteCollection();

        (new RouteLoader())->discoverRoutes($routes, $container, [self::DISCOVERY_DIR]);

        $this->assertTrue($container->has(AlphaController::class), 'a discovered controller is registered');
        $this->assertTrue($container->has(BetaController::class), 'a discovered controller is registered');
        $this->assertFalse($container->has(PlainService::class), 'a route-less class never enters the container');
    }

    private function container(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('not used');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
