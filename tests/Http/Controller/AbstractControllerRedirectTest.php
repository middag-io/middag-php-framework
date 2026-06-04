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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * redirectToRoute and redirectBack / flash controller helpers.
 *
 * @internal
 */
#[CoversNothing]
final class AbstractControllerRedirectTest extends TestCase
{
    #[Test]
    public function redirectToRouteResolvesNameAndFillsPlaceholders(): void
    {
        $controller = $this->controller($this->container(urlGenerator: $this->urlGenerator()));

        $response = $controller->callRedirectToRoute('tasks.show', ['id' => 5]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/tasks/5', $response->getTargetUrl());
    }

    #[Test]
    public function redirectToRouteThrowsWithoutAGenerator(): void
    {
        $controller = $this->controller($this->container());

        $this->expectException(RuntimeException::class);
        $controller->callRedirectToRoute('tasks.show', ['id' => 5]);
    }

    #[Test]
    public function redirectBackUsesRefererThenFallsBackToRoot(): void
    {
        $withReferer = $this->controller($this->container());
        $withReferer->setRequest(Request::create('/tasks', 'POST', server: ['HTTP_REFERER' => '/tasks/create']));
        $this->assertSame('/tasks/create', $withReferer->callRedirectBack()->getTargetUrl());

        $noReferer = $this->controller($this->container());
        $noReferer->setRequest(Request::create('/tasks', 'POST'));
        $this->assertSame('/', $noReferer->callRedirectBack()->getTargetUrl());
    }

    #[Test]
    public function flashDelegatesToBoundFlashBagAndIsNoOpOtherwise(): void
    {
        $flash = new FlashBag(new ArraySession());
        $this->controller($this->container(flash: $flash))->callFlash('success', 'Saved.');
        $this->assertSame(['success' => 'Saved.'], $flash->pull());

        // No FlashBag bound → silently no-op (no throw).
        $this->controller($this->container())->callFlash('success', 'Saved.');
        $this->addToAssertionCount(1);
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $routes = new RouteCollection();
        $routes->add('tasks.show', new Route('/tasks/{id}'));

        return new UrlGenerator($routes, new RequestContext());
    }

    private function controller(ContainerInterface $container): object
    {
        $controller = new class extends AbstractController {
            /** @param array<string, mixed> $params */
            public function callRedirectToRoute(string $name, array $params): RedirectResponse
            {
                return $this->redirectToRoute($name, $params);
            }

            public function callRedirectBack(): RedirectResponse
            {
                return $this->redirectBack();
            }

            public function callFlash(string $key, mixed $value): void
            {
                $this->flash($key, $value);
            }
        };

        $controller->setContainer($container);

        return $controller;
    }

    private function container(?UrlGeneratorInterface $urlGenerator = null, ?FlashBag $flash = null): ContainerInterface
    {
        return new class($urlGenerator, $flash) implements ContainerInterface {
            public function __construct(
                private readonly ?UrlGeneratorInterface $urlGenerator,
                private readonly ?FlashBag $flash,
            ) {}

            public function get(string $id): mixed
            {
                if ($id === UrlGeneratorInterface::class && $this->urlGenerator instanceof UrlGeneratorInterface) {
                    return $this->urlGenerator;
                }

                if ($id === FlashBag::class && $this->flash instanceof FlashBag) {
                    return $this->flash;
                }

                throw new RuntimeException('Unbound service: ' . $id);
            }

            public function has(string $id): bool
            {
                if ($id === UrlGeneratorInterface::class) {
                    return $this->urlGenerator instanceof UrlGeneratorInterface;
                }

                return $id === FlashBag::class && $this->flash instanceof FlashBag;
            }
        };
    }
}
