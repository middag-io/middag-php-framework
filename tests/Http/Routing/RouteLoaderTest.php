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
use Middag\Framework\Tests\Http\Fixture\PrefixedController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
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
