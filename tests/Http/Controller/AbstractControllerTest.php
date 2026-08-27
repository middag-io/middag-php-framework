<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Controller;

use Middag\Framework\Http\Controller\AbstractController;
use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Http\Session\FlashBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[CoversClass(AbstractController::class)]
final class AbstractControllerTest extends TestCase
{
    #[Test]
    public function setContainerWithoutARequestBuildsOneFromGlobals(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setContainer($this->container());

        $this->assertInstanceOf(Request::class, $controller->exposeRequest());
    }

    #[Test]
    public function setRequestPopulatesParamsAndPayload(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setRequest(Request::create('/tasks?foo=bar&page=2', 'POST', ['field' => 'value']));

        $this->assertSame(['foo' => 'bar', 'page' => '2'], $controller->exposeParams());
        $this->assertSame(['field' => 'value'], $controller->exposePayload());
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function lifecycleHooksAreInertNoOps(): void
    {
        $controller = new AbstractControllerTestController();

        $controller->handle();
        $controller->preHandle();
        $controller->setRequireLogin();
        $controller->setRequireCapabilities(['moodle/site:config'], 'course', 5);
    }

    #[Test]
    public function getServiceReturnsABoundService(): void
    {
        $service = new ArraySession();
        $controller = new AbstractControllerTestController();
        $controller->setContainer($this->container([ArraySession::class => $service]));

        $this->assertSame($service, $controller->exposeGetService(ArraySession::class));
    }

    #[Test]
    public function getServiceReturnsNullWhenUnbound(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setContainer($this->container());

        $this->assertNull($controller->exposeGetService('missing.service'));
    }

    #[Test]
    public function getServiceSwallowsContainerExceptionsAndReturnsNull(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setContainer($this->throwingContainer());

        $this->assertNull($controller->exposeGetService('explodes'));
    }

    #[Test]
    public function isJsonIsFalseWithoutARequest(): void
    {
        $this->assertFalse((new AbstractControllerTestController())->exposeIsJson());
    }

    #[Test]
    public function isJsonDetectsXmlHttpRequests(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setRequest(Request::create('/', 'GET', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));

        $this->assertTrue($controller->exposeIsJson());
    }

    #[Test]
    public function isJsonHonoursTheAcceptHeader(): void
    {
        $json = new AbstractControllerTestController();
        $json->setRequest(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/json']));
        $this->assertTrue($json->exposeIsJson());

        $html = new AbstractControllerTestController();
        $html->setRequest(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'text/html']));
        $this->assertFalse($html->exposeIsJson());
    }

    #[Test]
    public function responseReturnsJsonForArrayData(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setRequest(Request::create('/'));

        $response = $controller->exposeResponse(['ok' => true], Response::HTTP_ACCEPTED);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(202, $response->getStatusCode());
    }

    #[Test]
    public function responseReturnsJsonWhenTheClientWantsJson(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setRequest(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/json']));

        $this->assertInstanceOf(JsonResponse::class, $controller->exposeResponse('plain text'));
    }

    #[Test]
    public function responseReturnsPlainResponseForScalarHtmlRequests(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setRequest(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'text/html']));

        $response = $controller->exposeResponse('hello', Response::HTTP_CREATED);

        $this->assertNotInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('hello', $response->getContent());
    }

    #[Test]
    public function jsonResponseWrapsDataVerbatim(): void
    {
        $controller = new AbstractControllerTestController();

        $response = $controller->exposeJsonResponse(['id' => 7], Response::HTTP_CREATED);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('{"id":7}', $response->getContent());
    }

    #[Test]
    public function redirectDefaultsToFound(): void
    {
        $response = (new AbstractControllerTestController())->exposeRedirect('/home');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/home', $response->getTargetUrl());
    }

    #[Test]
    public function redirectToRouteResolvesNamedRoutes(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setContainer($this->container([UrlGeneratorInterface::class => $this->urlGenerator()]));

        $response = $controller->exposeRedirectToRoute('tasks.show', ['id' => 5]);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/tasks/5', $response->getTargetUrl());
    }

    #[Test]
    public function redirectToRouteThrowsWithoutAGenerator(): void
    {
        $controller = new AbstractControllerTestController();
        $controller->setContainer($this->container());

        $this->expectException(RuntimeException::class);
        $controller->exposeRedirectToRoute('tasks.show', ['id' => 5]);
    }

    #[Test]
    public function redirectBackPrefersTheRefererThenFallsBackToRoot(): void
    {
        $withReferer = new AbstractControllerTestController();
        $withReferer->setRequest(Request::create('/tasks', 'POST', server: ['HTTP_REFERER' => '/tasks/create']));
        $this->assertSame('/tasks/create', $withReferer->exposeRedirectBack()->getTargetUrl());

        // No request at all → the referer branch is skipped and "/" is used.
        $this->assertSame('/', (new AbstractControllerTestController())->exposeRedirectBack()->getTargetUrl());
    }

    #[Test]
    public function flashDelegatesToABoundBagAndIsOtherwiseANoOp(): void
    {
        $bag = new FlashBag(new ArraySession());
        $controller = new AbstractControllerTestController();
        $controller->setContainer($this->container([FlashBag::class => $bag]));
        $controller->exposeFlash('success', 'Saved.');

        $this->assertSame(['success' => 'Saved.'], $bag->pull());

        $noBag = new AbstractControllerTestController();
        $noBag->setContainer($this->container());
        $noBag->exposeFlash('success', 'Saved.');
        $this->addToAssertionCount(1);
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $routes = new RouteCollection();
        $routes->add('tasks.show', new Route('/tasks/{id}'));

        return new UrlGenerator($routes, new RequestContext());
    }

    /**
     * @param array<string, object> $services
     */
    private function container(array $services = []): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /**
             * @param array<string, object> $services
             */
            public function __construct(private readonly array $services) {}

            public function get(string $id): object
            {
                if (!isset($this->services[$id])) {
                    throw new RuntimeException('Unbound service: ' . $id);
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    private function throwingContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new class('container boom') extends RuntimeException implements ContainerExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return true;
            }
        };
    }
}

/**
 * @internal
 */
final class AbstractControllerTestController extends AbstractController
{
    public function exposeRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function exposeParams(): array
    {
        return $this->params;
    }

    /**
     * @return array<string, mixed>
     */
    public function exposePayload(): array
    {
        return $this->payload;
    }

    public function exposeGetService(string $serviceName): mixed
    {
        return $this->getService($serviceName);
    }

    public function exposeIsJson(): bool
    {
        return $this->isJson();
    }

    public function exposeResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse|Response
    {
        return $this->response($data, $status);
    }

    public function exposeJsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->jsonResponse($data, $status);
    }

    public function exposeRedirect(string $url, int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->redirect($url, $status);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function exposeRedirectToRoute(string $name, array $params = []): RedirectResponse
    {
        return $this->redirectToRoute($name, $params);
    }

    public function exposeRedirectBack(): RedirectResponse
    {
        return $this->redirectBack();
    }

    public function exposeFlash(string $key, mixed $value): void
    {
        $this->flash($key, $value);
    }
}
