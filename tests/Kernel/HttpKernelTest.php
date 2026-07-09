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

use Middag\Framework\Exception\MiddagNotFoundException;
use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Contract\ExceptionRendererInterface;
use Middag\Framework\Http\Contract\HttpKernelInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Tests\Http\Fixture\AuthPolicyController;
use Middag\Framework\Tests\Http\Fixture\BogusMiddlewareController;
use Middag\Framework\Tests\Http\Fixture\GatedController;
use Middag\Framework\Tests\Http\Fixture\MiddlewareController;
use Middag\Framework\Tests\Http\Fixture\OuterMiddleware;
use Middag\Framework\Tests\Http\Fixture\PlainActionController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use stdClass;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

/**
 * @internal
 */
#[CoversClass(HttpKernel::class)]
final class HttpKernelTest extends TestCase
{
    #[Test]
    public function implementsPsr15RequestHandler(): void
    {
        $this->assertTrue(is_subclass_of(HttpKernelInterface::class, RequestHandlerInterface::class));
        $this->assertInstanceOf(RequestHandlerInterface::class, $this->kernel(new RouteCollection()));
    }

    #[Test]
    public function returnsPsrResponseForMatchedRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('hello', new Route(
            '/hello',
            ['_controller' => fn (): JsonResponse => new JsonResponse(['msg' => 'world'])],
        ));

        $kernel = $this->kernel($routes);
        $request = new ServerRequest('GET', '/hello');

        $response = $kernel->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"msg":"world"}', (string) $response->getBody());
    }

    #[Test]
    public function returns404ForUnknownRoute(): void
    {
        $kernel = $this->kernel(new RouteCollection());
        $request = (new ServerRequest('GET', '/nope'))
            ->withHeader('Accept', 'application/json');

        $response = $kernel->handle($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('not_found', (string) $response->getBody());
    }

    #[Test]
    public function corsPreflightReturnsNoContent(): void
    {
        $kernel = $this->kernel(new RouteCollection());
        $request = new ServerRequest('OPTIONS', '/anything');

        $response = $kernel->handle($request);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * A method-restricted write route matches out of the box, without the
     * host pre-seeding the RequestContext. Previously this 405'd.
     */
    #[Test]
    public function matchesMethodRestrictedRouteWithoutContextSeeding(): void
    {
        $routes = new RouteCollection();
        $routes->add('create', (new Route(
            '/tasks',
            ['_controller' => fn (): JsonResponse => new JsonResponse(['ok' => true], 201)],
        ))->setMethods(['POST']));

        $kernel = $this->kernel($routes);
        $response = $kernel->handle(new ServerRequest('POST', '/tasks'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('"ok":true', (string) $response->getBody());
    }

    /**
     * A thrown MiddagValidationException reaches the client as 422 with its
     * field-level error map intact (previously dropped by the generic catch).
     */
    #[Test]
    public function validationExceptionSurfacesErrorMapAs422(): void
    {
        $routes = new RouteCollection();
        $routes->add('store', new Route('/store', ['_controller' => static function (): never {
            throw new MiddagValidationException('Validation failed', ['title' => 'Title is required']);
        }]));

        $kernel = $this->kernel($routes);
        $request = (new ServerRequest('GET', '/store'))->withHeader('Accept', 'application/json');
        $response = $kernel->handle($request);

        $this->assertSame(422, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('validation_failed', $body['error']);
        $this->assertSame(['title' => 'Title is required'], $body['errors']);
    }

    /**
     * A typed exception thrown inside a bus handler (wrapped by Symfony
     * Messenger in HandlerFailedException) is unwrapped and mapped to its declared
     * status (404), not a generic 500.
     */
    #[Test]
    public function unwrapsHandlerFailedExceptionToTypedStatus(): void
    {
        $routes = new RouteCollection();
        $routes->add('destroy', new Route('/destroy', ['_controller' => static function (): never {
            throw new HandlerFailedException(
                new Envelope(new stdClass()),
                [new MiddagNotFoundException('Task not found')],
            );
        }]));

        $kernel = $this->kernel($routes);
        $request = (new ServerRequest('GET', '/destroy'))->withHeader('Accept', 'application/json');
        $response = $kernel->handle($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('framework_error', (string) $response->getBody());
    }

    #[Test]
    public function delegatesErrorRenderingToInjectedRenderer(): void
    {
        $renderer = new class implements ExceptionRendererInterface {
            public function render(
                Throwable $throwable,
                Request $request,
                int $statusCode,
                string $errorCode,
                string $message,
                bool $isJson,
            ): Response {
                return new Response('custom:' . $errorCode . ':' . $statusCode, $statusCode);
            }
        };

        $psr17 = new Psr17Factory();
        $kernel = new HttpKernel(
            $this->container(),
            new RouteCollection(),
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
            false,
            $renderer,
        );

        $response = $kernel->handle(new ServerRequest('GET', '/nope'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('custom:not_found:404', (string) $response->getBody());
    }

    #[Test]
    public function injectsBasePathIntoContextSoGeneratedUrlsHonourTheEntryPoint(): void
    {
        $context = new RequestContext();
        $routes = new RouteCollection();
        $routes->add('home', new Route('/home', ['_controller' => static fn (): JsonResponse => new JsonResponse([])]));

        $psr17 = new Psr17Factory();
        $kernel = new HttpKernel(
            $this->container(),
            $routes,
            $context,
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
            false,
            null,
            '/local/middag/index.php',
        );

        $kernel->handle(new ServerRequest('GET', '/home'));

        self::assertSame('/local/middag/index.php', $context->getBaseUrl());
        self::assertSame(
            '/local/middag/index.php/home',
            (new UrlGenerator($routes, $context))->generate('home'),
        );
    }

    #[Test]
    public function nonResponseReturnIsWrappedInJson(): void
    {
        $routes = new RouteCollection();
        $routes->add('data', new Route('/data', ['_controller' => static fn (): array => ['ok' => 1]]));

        $response = $this->kernel($routes)->handle(new ServerRequest('GET', '/data'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"ok":1}', (string) $response->getBody());
    }

    #[Test]
    public function methodNotAllowedMapsTo405(): void
    {
        $routes = new RouteCollection();
        $routes->add('create', (new Route('/tasks', ['_controller' => static fn (): JsonResponse => new JsonResponse([])]))->setMethods(['POST']));

        $request = (new ServerRequest('GET', '/tasks'))->withHeader('Accept', 'application/json');
        $response = $this->kernel($routes)->handle($request);

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        self::assertStringContainsString('method_not_allowed', (string) $response->getBody());
    }

    #[Test]
    public function genericExceptionMapsToServerError500(): void
    {
        $routes = new RouteCollection();
        $routes->add('boom', new Route('/boom', ['_controller' => static function (): never {
            throw new RuntimeException('kaboom');
        }]));

        $request = (new ServerRequest('GET', '/boom'))->withHeader('Accept', 'application/json');
        $response = $this->kernel($routes)->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertStringContainsString('server_error', (string) $response->getBody());
    }

    #[Test]
    public function uncallableControllerYieldsServerError(): void
    {
        $routes = new RouteCollection();
        $routes->add('bad', new Route('/bad', ['_controller' => 'DefinitelyNotACallableClassName']));

        $request = (new ServerRequest('GET', '/bad'))->withHeader('Accept', 'application/json');
        $response = $this->kernel($routes)->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function xhrRequestNegotiatesJsonError(): void
    {
        // No Accept header, but X-Requested-With marks it as an XHR → JSON body.
        $request = (new ServerRequest('GET', '/nope'))->withHeader('X-Requested-With', 'XMLHttpRequest');
        $response = $this->kernel(new RouteCollection())->handle($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('not_found', (string) $response->getBody());
    }

    #[Test]
    public function stringControllerFqcnMethodIsResolvedFromContainer(): void
    {
        $routes = new RouteCollection();
        $routes->add('str', new Route('/str', ['_controller' => PlainActionController::class . '::show']));

        $container = $this->containerWith([PlainActionController::class => new PlainActionController()]);
        $response = $this->kernelWith($routes, $container)->handle(new ServerRequest('GET', '/str'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"via":"string-controller"}', (string) $response->getBody());
    }

    #[Test]
    public function containerBoundExceptionRendererIsPreferred(): void
    {
        $renderer = new class implements ExceptionRendererInterface {
            public function render(
                Throwable $throwable,
                Request $request,
                int $statusCode,
                string $errorCode,
                string $message,
                bool $isJson,
            ): Response {
                return new Response('bound:' . $errorCode, $statusCode);
            }
        };

        $container = $this->containerWith([ExceptionRendererInterface::class => $renderer]);
        $response = $this->kernelWith(new RouteCollection(), $container)->handle(new ServerRequest('GET', '/nope'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('bound:not_found', (string) $response->getBody());
    }

    #[Test]
    public function classLevelAuthAppliesToActionsWithoutTheirOwn(): void
    {
        $routes = new RouteCollection();
        $routes->add('guarded', new Route('/guarded', ['_controller' => [AuthPolicyController::class, 'guarded']]));

        $container = $this->containerWith([AuthPolicyController::class => new AuthPolicyController()]);
        $response = $this->kernelWith($routes, $container)->handle(new ServerRequest('GET', '/guarded'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('guarded', (string) $response->getBody());
    }

    #[Test]
    public function publicRouteDisablesControllerAuthentication(): void
    {
        $controller = new AuthPolicyController();
        $routes = new RouteCollection();
        $routes->add('open', new Route('/open', ['_controller' => [AuthPolicyController::class, 'open']]));

        $container = $this->containerWith([AuthPolicyController::class => $controller]);
        $response = $this->kernelWith($routes, $container)->handle(new ServerRequest('GET', '/open'));

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($controller->authDisabled, 'a public route signals the controller to skip its own auth');
    }

    #[Test]
    public function actionWithNoAuthAttributeAnywhereStaysUngated(): void
    {
        $routes = new RouteCollection();
        $routes->add('plain-auth', new Route('/plain-auth', ['_controller' => [GatedController::class, 'plain']]));

        $container = $this->containerWith([GatedController::class => new GatedController()]);
        $response = $this->kernelWith($routes, $container)->handle(new ServerRequest('GET', '/plain-auth'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('plain', (string) $response->getBody());
    }

    #[Test]
    public function middlewareRegisteredInContainerIsResolvedFromItInsteadOfBeingInstantiated(): void
    {
        $routes = new RouteCollection();
        $routes->add('mw', new Route('/mw', ['_controller' => [MiddlewareController::class, 'run']]));

        // OuterMiddleware (class-level #[Middleware]) is pre-registered in the
        // container; InnerMiddleware (method-level) is not — proving the two
        // resolution branches (container->get() vs `new $id()`) both run in the
        // same chain.
        $container = $this->containerWith([
            MiddlewareController::class => new MiddlewareController(),
            OuterMiddleware::class => new OuterMiddleware(),
        ]);
        $response = $this->kernelWith($routes, $container)->handle(new ServerRequest('GET', '/mw'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('inner outer', $response->getHeaderLine('X-Chain'));
    }

    #[Test]
    public function middlewareNotImplementingTheContractIsRejected(): void
    {
        $routes = new RouteCollection();
        $routes->add('bogus', new Route('/bogus', ['_controller' => [BogusMiddlewareController::class, 'run']]));

        $container = $this->containerWith([BogusMiddlewareController::class => new BogusMiddlewareController()]);
        $request = (new ServerRequest('GET', '/bogus'))->withHeader('Accept', 'application/json');
        $response = $this->kernelWith($routes, $container)->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function inertiaSafeMethodResponseIsLeftUnchanged(): void
    {
        $routes = new RouteCollection();
        $routes->add('page', new Route('/page', ['_controller' => static fn (): JsonResponse => new JsonResponse(['ok' => 1])]));

        // An Inertia GET (safe method) skips the 303 upgrade and returns as-is.
        $request = (new ServerRequest('GET', '/page'))->withHeader('X-Inertia', 'true');
        $response = $this->kernel($routes)->handle($request);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function emptyWrappedExceptionUnwrapsToItselfAsServerError(): void
    {
        $wrapper = new class('nothing wrapped') extends RuntimeException implements WrappedExceptionsInterface {
            public function getWrappedExceptions(?string $class = null, bool $recursive = false): array
            {
                return [];
            }
        };

        $routes = new RouteCollection();
        $routes->add('wrap', new Route('/wrap', ['_controller' => static function () use ($wrapper): never {
            throw $wrapper;
        }]));

        $request = (new ServerRequest('GET', '/wrap'))->withHeader('Accept', 'application/json');
        $response = $this->kernel($routes)->handle($request);

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertStringContainsString('server_error', (string) $response->getBody());
    }

    private function kernel(RouteCollection $routes): HttpKernel
    {
        return $this->kernelWith($routes, $this->container());
    }

    private function kernelWith(RouteCollection $routes, ContainerInterface $container): HttpKernel
    {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $container,
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
    }

    /**
     * @param array<string, object> $services
     */
    private function containerWith(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, object> $services */
            public function __construct(private array $services) {}

            public function get(string $id): mixed
            {
                return $this->services[$id] ?? throw new RuntimeException('service not found: ' . $id);
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    private function container(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('not used in this test');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
