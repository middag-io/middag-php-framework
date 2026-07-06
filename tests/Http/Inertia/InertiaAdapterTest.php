<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Inertia;

use Middag\Framework\Http\Inertia\DeferProp;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use Middag\Framework\Http\Inertia\MergeProp;
use Middag\Framework\Http\Inertia\OptionalProp;
use Middag\Ui\Envelope\Contract\ContractEnvelopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The static facade proxying the Inertia protocol helpers. It wires the
 * host-supplied URL resolver, proxies render/page/share/version to the
 * concrete Inertia helpers, wraps prop values in their v3 lazy/merge markers,
 * and builds the two redirect flavours (SPA 303 and hard-load 409/302).
 *
 * @internal
 */
#[CoversClass(InertiaAdapter::class)]
final class InertiaAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The facade's URL resolver is static process state; start each test
        // with it unconfigured so the "not configured" branch is reachable.
        $this->resetStaticProperty(InertiaAdapter::class, 'urlGenerator');
        unset($_SERVER['HTTP_X_INERTIA']);
    }

    protected function tearDown(): void
    {
        // Scrub every static seam this class mutates so sibling suites are not
        // contaminated (URL resolver, shared props, manual asset version, header).
        $this->resetStaticProperty(InertiaAdapter::class, 'urlGenerator');
        $this->resetStaticProperty(InertiaVersionManager::class, 'manualVersion');
        InertiaManager::flush();
        unset($_SERVER['HTTP_X_INERTIA']);

        parent::tearDown();
    }

    #[Test]
    public function setUrlGeneratorRegistersTheClosureUsedToResolveRoutes(): void
    {
        $captured = [];
        InertiaAdapter::setUrlGenerator(static function (string $route, array $params) use (&$captured): string {
            $captured = [$route, $params];

            return '/resolved/' . $route;
        });

        $response = InertiaAdapter::redirect('dashboard', ['id' => 5]);

        $this->assertSame(['dashboard', ['id' => 5]], $captured);
        $this->assertSame('/resolved/dashboard', $response->getTargetUrl());
    }

    #[Test]
    public function useUrlGeneratorWiresASymfonyUrlGeneratorAsTheResolver(): void
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('generate')
            ->with('profile', ['id' => 7])
            ->willReturn('/profile/7');

        InertiaAdapter::useUrlGenerator($generator);

        $this->assertSame('/profile/7', InertiaAdapter::redirect('profile', ['id' => 7])->getTargetUrl());
    }

    #[Test]
    public function redirectReturnsA303SeeOtherRedirect(): void
    {
        InertiaAdapter::setUrlGenerator(static fn (string $route, array $params): string => '/spa/' . $route);

        $response = InertiaAdapter::redirect('next-page');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        $this->assertSame('/spa/next-page', $response->getTargetUrl());
    }

    #[Test]
    public function locationReturns409WithInertiaLocationHeaderForInertiaClients(): void
    {
        InertiaAdapter::setUrlGenerator(static fn (string $route, array $params): string => '/external/target');
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $response = InertiaAdapter::location('external');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('/external/target', $response->headers->get('X-Inertia-Location'));
        $this->assertSame('X-Inertia', $response->headers->get('Vary'));
    }

    #[Test]
    public function locationReturnsAPlain302RedirectForNonInertiaClients(): void
    {
        InertiaAdapter::setUrlGenerator(static fn (string $route, array $params): string => '/external/target');

        $response = InertiaAdapter::location('external', ['q' => 1]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/external/target', $response->getTargetUrl());
    }

    #[Test]
    public function resolveUrlThrowsWhenNoUrlGeneratorIsConfigured(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('URL generator not configured');

        InertiaAdapter::redirect('anywhere');
    }

    #[Test]
    public function renderProxiesToTheInertiaFactoryAndReturnsAResponse(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $response = InertiaAdapter::render('ReportsDashboard', ['count' => 3]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $page = json_decode((string) $response->getContent(), true);

        $this->assertSame('ReportsDashboard', $page['component']);
        $this->assertSame(3, $page['props']['count']);
    }

    #[Test]
    public function pageFoldsAContractEnvelopeIntoAnInertiaResponse(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $contract = new class implements ContractEnvelopeInterface {
            /** @return array<string, mixed> */
            public function jsonSerialize(): array
            {
                return ['version' => '1', 'kind' => 'table'];
            }
        };

        $response = InertiaAdapter::page($contract, ['extra' => 9]);
        $page = json_decode((string) $response->getContent(), true);

        $this->assertSame('Fragment', $page['component']);
        $this->assertSame('table', $page['props']['contract']['kind']);
        $this->assertSame(9, $page['props']['extra']);
    }

    #[Test]
    public function shareRegistersAGlobalPropVisibleOnEveryResponse(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        InertiaAdapter::share('flash', 'saved-ok');

        $page = json_decode((string) InertiaAdapter::render('Dashboard')->getContent(), true);

        $this->assertSame('saved-ok', $page['props']['flash']);
    }

    #[Test]
    public function optionalWrapsTheCallbackInAnOptionalProp(): void
    {
        $prop = InertiaAdapter::optional(static fn (): string => 'lazy');

        $this->assertInstanceOf(OptionalProp::class, $prop);
        $this->assertSame('lazy', $prop->resolve());
    }

    #[Test]
    public function deferWrapsTheCallbackWithItsGroupAndRescueFlag(): void
    {
        $prop = InertiaAdapter::defer(static fn (): string => 'deferred', 'attributes', true);

        $this->assertInstanceOf(DeferProp::class, $prop);
        $this->assertSame('deferred', $prop->resolve());
        $this->assertSame('attributes', $prop->group());
        $this->assertTrue($prop->rescue());
    }

    #[Test]
    public function deferDefaultsToTheDefaultGroupWithoutRescue(): void
    {
        $prop = InertiaAdapter::defer(static fn (): int => 1);

        $this->assertSame('default', $prop->group());
        $this->assertFalse($prop->rescue());
    }

    #[Test]
    public function mergeWrapsTheValueAsAShallowMergePropWithMatchKeys(): void
    {
        $prop = InertiaAdapter::merge(['a'], ['id']);

        $this->assertInstanceOf(MergeProp::class, $prop);
        $this->assertFalse($prop->deep());
        $this->assertSame(['id'], $prop->matchOn());
        $this->assertSame(['a'], $prop->resolve());
    }

    #[Test]
    public function deepMergeWrapsTheValueAsADeepMergeProp(): void
    {
        $prop = InertiaAdapter::deepMerge(['a'], ['uuid']);

        $this->assertInstanceOf(MergeProp::class, $prop);
        $this->assertTrue($prop->deep());
        $this->assertSame(['uuid'], $prop->matchOn());
        $this->assertSame(['a'], $prop->resolve());
    }

    #[Test]
    public function versionOverridesTheInertiaAssetVersion(): void
    {
        InertiaAdapter::version('v9.9.9');

        $this->assertSame('v9.9.9', InertiaVersionManager::getVersion());
    }

    private function resetStaticProperty(string $class, string $property): void
    {
        (new ReflectionProperty($class, $property))->setValue(null, null);
    }
}
