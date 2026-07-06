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
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\TranslatableMessage;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
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
#[CoversClass(HttpKernel::class)]
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

    #[Test]
    public function validationErrorsSerialiseToMessageKeyDomainParams(): void
    {
        $routes = new RouteCollection();
        $routes->add('store', (new Route('/tasks', ['_controller' => static function (): never {
            throw new MiddagValidationException('Validation failed', [
                'title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.'),
            ]);
        }]))->setMethods(['POST']));

        $response = $this->kernel($routes)->handle(
            (new ServerRequest('POST', '/tasks'))->withHeader('Accept', 'application/json'),
        );

        $this->assertSame(422, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame('validation_failed', $payload['error']);
        $first = $payload['errors']['title'];
        $this->assertArrayHasKey('message', $first);
        $this->assertArrayHasKey('key', $first);
        $this->assertSame('validators', $first['domain']);
        $this->assertArrayHasKey('params', $first);
        $this->assertStringStartsWith('validation.', $first['key']);
    }

    #[Test]
    public function translatorBoundInContainerResolvesTheMessage(): void
    {
        $translator = new class implements TranslatorInterface {
            public function get(string $key, string $component = '', array $params = []): string
            {
                return 'PT:' . $key;
            }

            public function has(string $key, string $component = ''): bool
            {
                return true;
            }
        };

        $routes = new RouteCollection();
        $routes->add('store', (new Route('/tasks', ['_controller' => static function (): never {
            throw new MiddagValidationException('Validation failed', [
                'title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.'),
            ]);
        }]))->setMethods(['POST']));

        $response = $this->kernel($routes, null, $translator)->handle(
            (new ServerRequest('POST', '/tasks'))->withHeader('Accept', 'application/json'),
        );

        $this->assertSame(422, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $first = $payload['errors']['title'];
        $this->assertSame('PT:validation.not_blank', $first['message']);
    }

    private function kernel(RouteCollection $routes, ?FlashBag $flash = null, ?TranslatorInterface $translator = null): HttpKernel
    {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $this->container($flash, $translator),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
    }

    private function container(?FlashBag $flash, ?TranslatorInterface $translator = null): ContainerInterface
    {
        return new class($flash, $translator) implements ContainerInterface {
            public function __construct(
                private readonly ?FlashBag $flash,
                private readonly ?TranslatorInterface $translator,
            ) {}

            public function get(string $id): mixed
            {
                if ($id === FlashBag::class && $this->flash instanceof FlashBag) {
                    return $this->flash;
                }

                if ($id === TranslatorInterface::class && $this->translator instanceof TranslatorInterface) {
                    return $this->translator;
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                return ($id === FlashBag::class && $this->flash instanceof FlashBag)
                    || ($id === TranslatorInterface::class && $this->translator instanceof TranslatorInterface);
            }
        };
    }
}
