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

use Middag\Framework\Http\Contract\ExceptionRendererInterface;
use Middag\Framework\Http\Contract\HttpKernelInterface;
use Middag\Framework\Http\HttpKernelFactory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

/**
 * @internal
 */
#[CoversClass(HttpKernelFactory::class)]
final class HttpKernelFactoryTest extends TestCase
{
    #[Test]
    public function theCreatedKernelMatchesARouteAndAnswersOverPsr7(): void
    {
        $routes = new RouteCollection();
        $routes->add('ping', new Route('/ping', [
            '_controller' => static fn (): JsonResponse => new JsonResponse(['pong' => true]),
        ]));

        $kernel = (new HttpKernelFactory())->create($this->emptyContainer(), $routes);

        $response = $kernel->handle(new ServerRequest('GET', '/ping'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"pong":true}', (string) $response->getBody());
    }

    #[Test]
    public function theBasePathIsWrittenIntoTheSharedRequestContext(): void
    {
        $routes = new RouteCollection();
        $routes->add('home', new Route('/home', ['_controller' => static fn (): JsonResponse => new JsonResponse([])]));

        $context = new RequestContext();

        $kernel = (new HttpKernelFactory())->create($this->emptyContainer(), $routes, $context, basePath: '/app/index.php');
        $kernel->handle(new ServerRequest('GET', '/home'));

        self::assertSame('/app/index.php', $context->getBaseUrl());
    }

    #[Test]
    public function aCustomExceptionRendererReplacesTheDefaultErrorPage(): void
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
                return new Response('custom:' . $statusCode, $statusCode);
            }
        };

        $kernel = (new HttpKernelFactory())->create($this->emptyContainer(), new RouteCollection(), exceptionRenderer: $renderer);

        $response = $kernel->handle(new ServerRequest('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('custom:404', (string) $response->getBody());
    }

    #[Test]
    public function aContainerBuildsTheKernelThroughTheFactory(): void
    {
        $container = new ContainerBuilder();
        $container->register(RouteCollection::class, RouteCollection::class);
        $container->register(HttpKernelFactory::class, HttpKernelFactory::class);
        $container->register('kernel', HttpKernelInterface::class)
            ->setFactory([new Reference(HttpKernelFactory::class), 'create'])
            ->setArguments([new Reference('service_container'), new Reference(RouteCollection::class)])
            ->setPublic(true);
        $container->compile();

        self::assertInstanceOf(HttpKernelInterface::class, $container->get('kernel'));
    }

    private function emptyContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new RuntimeException('no service: ' . $id);
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
