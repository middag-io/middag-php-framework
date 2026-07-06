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
use Middag\Framework\Kernel\Module\AbstractModule;
use Middag\Framework\Tests\Http\Fixture\PrefixedController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class-level #[Route] contributes a path + name prefix, and the generated
 * name fallback is fully-qualified (collision-resistant).
 *
 * @internal
 */
#[CoversClass(RouteLoader::class)]
final class RouteLoaderTest extends TestCase
{
    #[Test]
    public function classPrefixComposesPathAndName(): void
    {
        $routes = $this->load();

        $users = $routes->get('admin.users');
        $this->assertNotNull($users);
        $this->assertSame('/admin/users', $users->getPath());
    }

    #[Test]
    public function nameFallbackIsFullyQualifiedAndPrefixed(): void
    {
        $routes = $this->load();

        $expected = 'admin.' . strtolower(str_replace('\\', '_', PrefixedController::class) . '_stats');
        $stats = $routes->get($expected);

        $this->assertNotNull($stats, 'fallback route name should be the FQCN slug with the class name prefix');
        $this->assertSame('/admin/stats', $stats->getPath());
    }

    #[Test]
    public function nullMissingAndAbstractClassesAreSkipped(): void
    {
        $loader = new RouteLoader();
        $container = $this->container();

        $routes = new RouteCollection();
        $loader->loadRoutes($routes, $container, null);
        $loader->loadRoutes($routes, $container, 'Totally\\Missing\\Controller');
        $loader->loadRoutes($routes, $container, AbstractModule::class);

        $this->assertCount(0, $routes, 'null, missing and abstract classes register no routes');
    }

    #[Test]
    public function controllerIsRegisteredInAContainerBuilder(): void
    {
        $container = new ContainerBuilder();
        $routes = new RouteCollection();

        (new RouteLoader())->loadRoutes($routes, $container, PrefixedController::class);

        $this->assertTrue($container->has(PrefixedController::class));
        $definition = $container->getDefinition(PrefixedController::class);
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->isPublic());
    }

    #[Test]
    public function constructorAndDestructorAreSkippedAndNoClassPrefixLeavesPathBare(): void
    {
        $controller = new class {
            public function __construct() {}

            public function __destruct() {}

            #[Route('/thing', name: 'thing')]
            public function index(): void {}
        };

        // A plain (non-ContainerBuilder) container skips the service registration
        // branch — the anonymous class name is not a valid service id anyway.
        $routes = new RouteCollection();
        (new RouteLoader())->loadRoutes($routes, $this->container(), $controller::class);

        $route = $routes->get('thing');
        $this->assertNotNull($route, 'the #[Route] method is registered while __construct/__destruct are skipped');
        // No class-level #[Route] → joinPath returns the bare method path.
        $this->assertSame('/thing', $route->getPath());
    }

    private function load(): RouteCollection
    {
        $routes = new RouteCollection();
        (new RouteLoader())->loadRoutes($routes, $this->container(), PrefixedController::class);

        return $routes;
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
