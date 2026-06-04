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

use Middag\Framework\Http\Auth\SessionAuthenticator;
use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Http\Session\ArraySession;
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
 * The OSS #[Auth] gate. A login-required action denies unauthenticated
 * access (per request type) only when an AuthenticatorInterface is bound; it is
 * inert otherwise so host-delegated auth is untouched.
 *
 * @internal
 */
#[CoversNothing]
final class AuthGateTest extends TestCase
{
    #[Test]
    public function authenticatedUserReachesProtectedAction(): void
    {
        $response = $this->dispatch('secret', $this->loggedIn(), inertia: false, json: true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('secret', (string) $response->getBody());
    }

    #[Test]
    public function unauthenticatedJsonRequestGets401(): void
    {
        $response = $this->dispatch('secret', $this->loggedOut(), inertia: false, json: true);

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function unauthenticatedBrowserVisitRedirectsToLogin(): void
    {
        $response = $this->dispatch('secret', $this->loggedOut(), inertia: false, json: false);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function unauthenticatedInertiaRequestGets409Location(): void
    {
        $response = $this->dispatch('secret', $this->loggedOut(), inertia: true, json: false);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('X-Inertia-Location'));
    }

    #[Test]
    public function gateIsInertWhenNoAuthenticatorBound(): void
    {
        $response = $this->dispatch('secret', null, inertia: false, json: true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('secret', (string) $response->getBody());
    }

    #[Test]
    public function publicActionIsReachableWhileUnauthenticated(): void
    {
        $response = $this->dispatch('open', $this->loggedOut(), inertia: false, json: true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('open', (string) $response->getBody());
    }

    private function dispatch(string $action, ?AuthenticatorInterface $authenticator, bool $inertia, bool $json): ResponseInterface
    {
        $routes = new RouteCollection();
        $routes->add($action, new Route('/' . $action, ['_controller' => [GatedController::class, $action]]));

        $kernel = $this->kernel($routes, $authenticator);

        $request = new ServerRequest('GET', '/' . $action);
        if ($inertia) {
            $request = $request->withHeader('X-Inertia', 'true');
        }
        if ($json) {
            $request = $request->withHeader('Accept', 'application/json');
        }

        return $kernel->handle($request);
    }

    private function loggedIn(): SessionAuthenticator
    {
        $auth = new SessionAuthenticator(new ArraySession());
        $auth->login(42, ['name' => 'Ada']);

        return $auth;
    }

    private function loggedOut(): SessionAuthenticator
    {
        return new SessionAuthenticator(new ArraySession());
    }

    private function kernel(RouteCollection $routes, ?AuthenticatorInterface $authenticator): HttpKernel
    {
        $psr17 = new Psr17Factory();

        return new HttpKernel(
            $this->container($authenticator),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
    }

    private function container(?AuthenticatorInterface $authenticator): ContainerInterface
    {
        return new class($authenticator) implements ContainerInterface {
            public function __construct(private readonly ?AuthenticatorInterface $authenticator) {}

            public function get(string $id): mixed
            {
                if ($id === GatedController::class) {
                    return new GatedController();
                }

                if ($id === AuthenticatorInterface::class && $this->authenticator instanceof AuthenticatorInterface) {
                    return $this->authenticator;
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                if ($id === GatedController::class) {
                    return true;
                }

                return $id === AuthenticatorInterface::class && $this->authenticator instanceof AuthenticatorInterface;
            }
        };
    }
}
