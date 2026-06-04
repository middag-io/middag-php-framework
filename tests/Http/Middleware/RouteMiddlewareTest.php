<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Middleware;

use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Tests\Http\Fixture\MiddlewareController;
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
 * #[Middleware] per-route chains. Class- and method-level middleware wrap
 * the action (class outermost), a middleware can short-circuit before the action,
 * and unregistered middleware classes are instantiated on the fly.
 *
 * @internal
 */
#[CoversNothing]
final class RouteMiddlewareTest extends TestCase
{
    #[Test]
    public function classAndMethodMiddlewareWrapTheActionOutermostFirst(): void
    {
        $response = $this->dispatch('run');

        self::assertSame(200, $response->getStatusCode());
        // Inner (method-level) post-processes before Outer (class-level): the
        // class-level middleware wraps the method-level one.
        self::assertSame('inner outer', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function middlewareCanShortCircuitBeforeTheAction(): void
    {
        $response = $this->dispatch('blocked');

        self::assertSame(403, $response->getStatusCode());
    }

    private function dispatch(string $action): ResponseInterface
    {
        $routes = new RouteCollection();
        $routes->add($action, new Route('/' . $action, ['_controller' => [MiddlewareController::class, $action]]));

        $psr17 = new Psr17Factory();
        $kernel = new HttpKernel(
            $this->container(),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );

        return $kernel->handle(new ServerRequest('GET', '/' . $action));
    }

    private function container(): ContainerInterface
    {
        // Binds only the controller — the middleware classes are unregistered, so
        // the kernel must fall back to instantiating them directly.
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                if ($id === MiddlewareController::class) {
                    return new MiddlewareController();
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                return $id === MiddlewareController::class;
            }
        };
    }
}
