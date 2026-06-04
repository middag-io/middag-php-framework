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
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
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
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

/**
 * @internal
 */
#[CoversNothing]
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

    private function kernel(RouteCollection $routes): HttpKernel
    {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $this->container(),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
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
