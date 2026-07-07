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

use Middag\Framework\Http\Contract\AuthenticatorInterface;
use Middag\Framework\Http\HttpKernel;
use Middag\Framework\Tests\Http\Fixture\CapabilityAwareController;
use Middag\Framework\Tests\Http\Fixture\GatedController;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
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
 * The kernel forwards the rich #[Auth] CapabilityRequirement list to controllers
 * that opt in via CapabilityRequirementAwareInterface, without disturbing the
 * legacy string surface for controllers that do not.
 *
 * @internal
 */
#[CoversClass(HttpKernel::class)]
final class CapabilityRequirementPropagationTest extends TestCase
{
    #[Test]
    public function richRequirementsReachOptInController(): void
    {
        $response = $this->dispatch(CapabilityAwareController::class, 'guarded');

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(['moodle/course:view', 'mod/quiz:attempt'], $payload['keys']);
    }

    #[Test]
    public function controllerWithoutOptInStillReachesAction(): void
    {
        // GatedController does not implement CapabilityRequirementAwareInterface;
        // the kernel must skip the rich call and leave the legacy path intact.
        $response = $this->dispatch(GatedController::class, 'secret');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('secret', (string) $response->getBody());
    }

    private function dispatch(string $controllerClass, string $action): ResponseInterface
    {
        $routes = new RouteCollection();
        $routes->add($action, new Route('/' . $action, ['_controller' => [$controllerClass, $action]]));

        $psr17 = new Psr17Factory();
        $kernel = new HttpKernel(
            $this->container($controllerClass),
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );

        // No AuthenticatorInterface bound: the login gate is inert, so the
        // action runs and we can observe what the kernel wired beforehand.
        $request = (new ServerRequest('GET', '/' . $action))->withHeader('Accept', 'application/json');

        return $kernel->handle($request);
    }

    private function container(string $controllerClass): ContainerInterface
    {
        return new class($controllerClass) implements ContainerInterface {
            public function __construct(private readonly string $controllerClass) {}

            public function get(string $id): mixed
            {
                if ($id === $this->controllerClass) {
                    return new $this->controllerClass();
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                return $id === $this->controllerClass;
            }
        };
    }
}
