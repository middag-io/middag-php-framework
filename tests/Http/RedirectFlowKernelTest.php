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

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Http\Session\FlashBag;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * 301/302→303 enforcement and validation post-redirect-get at the
 * kernel boundary.
 *
 * @internal
 */
#[CoversNothing]
final class RedirectFlowKernelTest extends TestCase
{
    #[Test]
    public function inertiaRedirectAfterUnsafeMethodIsUpgradedTo303(): void
    {
        $routes = new RouteCollection();
        $routes->add('store', (new Route(
            '/tasks',
            ['_controller' => fn (): RedirectResponse => new RedirectResponse('/tasks', 302)],
        ))->setMethods(['POST']));

        $response = $this->kernel($routes)->handle(
            (new ServerRequest('POST', '/tasks'))->withHeader('X-Inertia', 'true'),
        );

        $this->assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function inertiaPermanentRedirectAfterUnsafeMethodIsUpgradedTo303(): void
    {
        // A 301 after a mutation is cacheable and would replay the unsafe
        // method; the kernel promotes it to 303 like a 302.
        $routes = new RouteCollection();
        $routes->add('store', (new Route(
            '/tasks',
            ['_controller' => fn (): RedirectResponse => new RedirectResponse('/tasks', 301)],
        ))->setMethods(['POST']));

        $response = $this->kernel($routes)->handle(
            (new ServerRequest('POST', '/tasks'))->withHeader('X-Inertia', 'true'),
        );

        $this->assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function nonInertiaRedirectKeepsItsStatus(): void
    {
        $routes = new RouteCollection();
        $routes->add('store', (new Route(
            '/tasks',
            ['_controller' => fn (): RedirectResponse => new RedirectResponse('/tasks', 302)],
        ))->setMethods(['POST']));

        $response = $this->kernel($routes)->handle(new ServerRequest('POST', '/tasks'));

        $this->assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function validationOnBrowserRequestFlashesErrorsAndRedirectsBack(): void
    {
        $flash = new FlashBag(new ArraySession());

        $routes = new RouteCollection();
        $routes->add('store', (new Route('/tasks', ['_controller' => static function (): never {
            throw new MiddagValidationException('Validation failed', ['title' => 'Title is required']);
        }]))->setMethods(['POST']));

        $response = $this->kernel($routes, $flash)->handle(
            (new ServerRequest('POST', '/tasks'))->withHeader('Referer', '/tasks/create'),
        );

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/tasks/create', $response->getHeaderLine('Location'));
        $this->assertSame(['title' => 'Title is required'], $flash->pullErrors());
    }

    #[Test]
    public function validationNestsErrorsUnderTheRequestedInertiaErrorBag(): void
    {
        $flash = new FlashBag(new ArraySession());

        $routes = new RouteCollection();
        $routes->add('store', (new Route('/tasks', ['_controller' => static function (): never {
            throw new MiddagValidationException('Validation failed', ['title' => 'Title is required']);
        }]))->setMethods(['POST']));

        $response = $this->kernel($routes, $flash)->handle(
            (new ServerRequest('POST', '/tasks'))
                ->withHeader('Referer', '/tasks/create')
                ->withHeader('X-Inertia-Error-Bag', 'createTask'),
        );

        $this->assertSame(303, $response->getStatusCode());
        // useForm('createTask') reads its own scoped errors.
        $this->assertSame(['createTask' => ['title' => 'Title is required']], $flash->pullErrors());
    }

    #[Test]
    public function jsonValidationNestsErrorsUnderTheRequestedInertiaErrorBag(): void
    {
        // Real Inertia XHRs are classified as JSON, so they hit the 422 branch —
        // the error bag must nest there too, not only on the flash/303 path.
        $routes = new RouteCollection();
        $routes->add('store', (new Route('/tasks', ['_controller' => static function (): never {
            throw new MiddagValidationException('Validation failed', ['title' => 'Title is required']);
        }]))->setMethods(['POST']));

        $response = $this->kernel($routes)->handle(
            (new ServerRequest('POST', '/tasks'))
                ->withHeader('Accept', 'application/json')
                ->withHeader('X-Inertia-Error-Bag', 'createTask'),
        );

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(['createTask' => ['title' => 'Title is required']], $body['errors']);
    }

    #[Test]
    public function validationStillReturns422ForJsonClients(): void
    {
        $flash = new FlashBag(new ArraySession());

        $routes = new RouteCollection();
        $routes->add('store', (new Route('/tasks', ['_controller' => static function (): never {
            throw new MiddagValidationException('Validation failed', ['title' => 'Title is required']);
        }]))->setMethods(['POST']));

        $response = $this->kernel($routes, $flash)->handle(
            (new ServerRequest('POST', '/tasks'))->withHeader('Accept', 'application/json'),
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    private function kernel(RouteCollection $routes, ?FlashBag $flash = null): HttpKernel
    {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $this->container($flash),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
    }

    private function container(?FlashBag $flash): ContainerInterface
    {
        return new class($flash) implements ContainerInterface {
            public function __construct(private readonly ?FlashBag $flash) {}

            public function get(string $id): mixed
            {
                if ($id === FlashBag::class && $this->flash instanceof FlashBag) {
                    return $this->flash;
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                return $id === FlashBag::class && $this->flash instanceof FlashBag;
            }
        };
    }
}
