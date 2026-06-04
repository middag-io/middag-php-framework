<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http;

use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Tests\Http\Fixture\GatedController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Every kernel-emitted response varies on X-Inertia, so a shared
 * cache never serves a document navigation a JSON page object (or vice versa)
 * for one URL. Covers both the happy path and a kernel-generated error.
 *
 * @internal
 */
#[CoversNothing]
final class KernelVaryTest extends TestCase
{
    #[Test]
    public function okResponseCarriesInertiaVary(): void
    {
        $response = $this->dispatch(requestPath: '/open');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('X-Inertia', $response->getHeaderLine('Vary'));
    }

    #[Test]
    public function notFoundResponseAlsoCarriesInertiaVary(): void
    {
        // No route matches /missing → 404; it must still carry the Vary header so
        // an error page is cached per request type too.
        $response = $this->dispatch(requestPath: '/missing');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('X-Inertia', $response->getHeaderLine('Vary'));
    }

    private function dispatch(string $requestPath): ResponseInterface
    {
        $routes = new RouteCollection();
        $routes->add('open', new Route('/open', ['_controller' => [GatedController::class, 'open']]));

        $psr17 = new Psr17Factory();
        $kernel = new HttpKernel(
            $this->container(),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );

        return $kernel->handle(new ServerRequest('GET', $requestPath));
    }

    private function container(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                if ($id === GatedController::class) {
                    return new GatedController();
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                return $id === GatedController::class;
            }
        };
    }
}
