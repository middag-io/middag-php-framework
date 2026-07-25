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
use Middag\Framework\Http\Routing\RouteMiddlewareStack;
use Middag\Framework\Tests\Http\Fixture\GroupMiddleware;
use Middag\Framework\Tests\Http\Fixture\InnerMiddleware;
use Middag\Framework\Tests\Http\Fixture\MiddlewareController;
use Middag\Framework\Tests\Http\Fixture\OuterMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use stdClass;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Per-route middleware chains end to end, from both declaration sources.
 *
 * `#[Middleware]` attributes (class- and method-level) and the imperative
 * `_middleware` / `_without_middleware` route defaults a registrar writes must
 * compose into ONE chain, on BOTH handler shapes — `[instance, method]` and a bare
 * `Closure`. Order is route defaults → class attribute → method attribute →
 * action, duplicates run once, exclusions really remove, and an id that cannot be
 * resolved aborts the request instead of vanishing from the chain.
 *
 * @internal
 */
#[CoversClass(HttpKernel::class)]
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

    #[Test]
    public function routeDefaultMiddlewareWrapsTheAttributeChainOnAnArrayController(): void
    {
        $response = $this->dispatch('run', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => [GroupMiddleware::class],
        ]);

        self::assertSame(200, $response->getStatusCode());
        // Post-processing runs innermost-first, so the stamp order is the reverse
        // of the chain: method attribute, class attribute, then route default —
        // i.e. the route default is the OUTERMOST entry.
        self::assertSame('inner outer group', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function routeDefaultMiddlewareRunsOnAClosureHandler(): void
    {
        // The regression this path exists for: a closure has no class or method to
        // reflect, so before the fix it ran through no chain at all.
        $routes = new RouteCollection();
        $routes->add('closure', new Route('/closure', [
            '_controller' => static fn (): JsonResponse => new JsonResponse(['ok' => true]),
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => [GroupMiddleware::class, OuterMiddleware::class],
        ]));

        $response = $this->kernel($routes)->handle(new ServerRequest('GET', '/closure'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('outer group', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function aClosureHandlerStillReceivesItsRouteParametersUnpolluted(): void
    {
        // The middleware defaults are kernel vocabulary: leaving them in the matched
        // parameters splatted them positionally into the closure and turned a
        // middleware-protected closure route into a hard 500.
        $routes = new RouteCollection();
        $routes->add('show', new Route('/show/{id}', [
            '_controller' => static fn (string $id): JsonResponse => new JsonResponse(['id' => $id]),
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => [GroupMiddleware::class],
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => [],
        ]));

        $response = $this->kernel($routes)->handle(new ServerRequest('GET', '/show/42'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"id":"42"}', (string) $response->getBody());
        self::assertSame('group', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function aClosureReturningRawDataIsStillWrappedWhenTheChainRuns(): void
    {
        $routes = new RouteCollection();
        $routes->add('raw', new Route('/raw', [
            '_controller' => static fn (): array => ['ok' => true],
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => [GroupMiddleware::class],
        ]));

        $response = $this->kernel($routes)->handle(new ServerRequest('GET', '/raw'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"ok":true}', (string) $response->getBody());
        self::assertSame('group', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function anIdDeclaredByBothTheRouteAndTheAttributeRunsOnce(): void
    {
        // OuterMiddleware is the class-level attribute AND named by the route
        // default. A duplicated chain would stamp `outer` twice.
        $response = $this->dispatch('run', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => [OuterMiddleware::class, GroupMiddleware::class],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('inner group outer', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function withoutMiddlewareRemovesAMethodLevelAttributeMiddleware(): void
    {
        // InnerMiddleware is declared by #[Middleware] on the action — invisible to
        // the registrar — and is still removed by the exclusion list.
        $response = $this->dispatch('run', [
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => [InnerMiddleware::class],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('outer', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function withoutMiddlewareRemovesAnInheritedGroupMiddleware(): void
    {
        // The group contributed GroupMiddleware; the route opted out of it while
        // keeping the rest of the inherited chain.
        $response = $this->dispatch('run', [
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => [GroupMiddleware::class, OuterMiddleware::class],
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => [GroupMiddleware::class],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('inner outer', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function withoutMiddlewareCanEmptyTheChainEntirely(): void
    {
        $response = $this->dispatch('run', [
            RouteMiddlewareStack::WITHOUT_MIDDLEWARE_DEFAULT => [OuterMiddleware::class, InnerMiddleware::class],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function anUnresolvableMiddlewareIdAbortsTheRequestNamingTheRouteAndTheId(): void
    {
        $routes = new RouteCollection();
        $routes->add('admin.store', new Route('/admin', [
            '_controller' => static fn (): JsonResponse => new JsonResponse(['ok' => true]),
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => ['app.middleware.typo'],
        ]));

        $response = $this->kernel($routes, debug: true)
            ->handle((new ServerRequest('GET', '/admin'))->withHeader('Accept', 'application/json'));

        // Loud, not silent: an unresolvable guard must never be skipped.
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $message = $this->debugMessage($response);
        self::assertStringContainsString('app.middleware.typo', $message);
        self::assertStringContainsString('admin.store', $message);
        self::assertStringContainsString('cannot be resolved', $message);
    }

    #[Test]
    public function aRouteDefaultMiddlewareBreakingTheContractIsRejectedNamingTheRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('admin.index', new Route('/admin', [
            '_controller' => static fn (): JsonResponse => new JsonResponse(['ok' => true]),
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => [stdClass::class],
        ]));

        $response = $this->kernel($routes, debug: true)
            ->handle((new ServerRequest('GET', '/admin'))->withHeader('Accept', 'application/json'));

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $message = $this->debugMessage($response);
        self::assertStringContainsString(stdClass::class, $message);
        self::assertStringContainsString('admin.index', $message);
        self::assertStringContainsString('must implement', $message);
    }

    #[Test]
    public function aMalformedMiddlewareDefaultAbortsTheRequest(): void
    {
        $routes = new RouteCollection();
        $routes->add('admin.broken', new Route('/admin', [
            '_controller' => static fn (): JsonResponse => new JsonResponse(['ok' => true]),
            RouteMiddlewareStack::MIDDLEWARE_DEFAULT => 'not-a-list',
        ]));

        $response = $this->kernel($routes, debug: true)
            ->handle((new ServerRequest('GET', '/admin'))->withHeader('Accept', 'application/json'));

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertStringContainsString('admin.broken', $this->debugMessage($response));
    }

    /**
     * Dispatch an action of {@see MiddlewareController} with optional route defaults.
     *
     * @param array<string, mixed> $defaults extra Symfony route defaults
     */
    private function dispatch(string $action, array $defaults = []): ResponseInterface
    {
        $routes = new RouteCollection();
        $routes->add($action, new Route('/' . $action, [
            '_controller' => [MiddlewareController::class, $action],
            ...$defaults,
        ]));

        return $this->kernel($routes)->handle(new ServerRequest('GET', '/' . $action));
    }

    private function kernel(RouteCollection $routes, bool $debug = false): HttpKernel
    {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $this->container(),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
            $debug,
        );
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

    /**
     * The exception message the debug renderer embeds in the JSON error envelope.
     */
    private function debugMessage(ResponseInterface $response): string
    {
        $body = json_decode((string) $response->getBody(), true);

        self::assertIsArray($body);
        self::assertArrayHasKey('debug', $body);
        self::assertIsArray($body['debug']);
        self::assertArrayHasKey('message', $body['debug']);
        self::assertIsString($body['debug']['message']);

        return $body['debug']['message'];
    }
}
