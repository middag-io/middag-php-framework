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

use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaResponse;
use Middag\Ui\Envelope\Contract\ContractEnvelopeInterface;
use Middag\Ui\Page\Contract\PageContractInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The UI-contract → Inertia bridge. A middag-io/ui contract envelope is
 * returned as an Inertia response with the envelope folded into a reserved
 * `contract` prop and the component derived from the envelope kind.
 *
 * @internal
 */
#[CoversClass(InertiaFactory::class)]
final class InertiaFactoryTest extends TestCase
{
    #[Test]
    public function pageContractFoldsIntoContractPropUnderPageComponent(): void
    {
        $contract = new class implements PageContractInterface {
            /** @return array<string, mixed> */
            public function jsonSerialize(): array
            {
                return ['version' => '1', 'shell' => 'product', 'marker' => 'PAGE'];
            }
        };

        $page = $this->renderInertiaJson(InertiaFactory::page($contract, ['extra' => 1], $this->inertiaRequest()));

        $this->assertSame('Page', $page['component']);
        $this->assertSame('PAGE', $page['props']['contract']['marker']);
        $this->assertSame(1, $page['props']['extra']);
    }

    #[Test]
    public function fragmentEnvelopeResolvesToFragmentComponent(): void
    {
        $fragment = new class implements ContractEnvelopeInterface {
            /** @return array<string, mixed> */
            public function jsonSerialize(): array
            {
                return ['version' => '1', 'kind' => 'table'];
            }
        };

        $page = $this->renderInertiaJson(InertiaFactory::page($fragment, [], $this->inertiaRequest()));

        $this->assertSame('Fragment', $page['component']);
        $this->assertSame('table', $page['props']['contract']['kind']);
    }

    #[Test]
    public function appIdDefaultsToAppAndIsOverridableByTheCompositionRoot(): void
    {
        // Default: Inertia's conventional mount id, so a generic host needs no config.
        $this->assertSame('app', InertiaFactory::getAppId());

        // A product composition root overrides it; the generic default shell then
        // emits a matching mount div + Inertia v3 page-data <script data-page>.
        InertiaFactory::setAppId('middag-app');

        try {
            $this->assertSame('middag-app', InertiaFactory::getAppId());

            $html = (string) InertiaFactory::render('Dashboard', [], Request::create('/x'))
                ->toResponse()->getContent();

            $this->assertStringContainsString('<div id="middag-app">', $html);
            $this->assertStringContainsString(
                '<script type="application/json" data-page="middag-app">',
                $html,
            );
        } finally {
            // Restore the static default so sibling tests (which assert the "app"
            // shell) are not contaminated by this override.
            InertiaFactory::setAppId('app');
        }
    }

    #[Test]
    public function setHtmlBootstrapRegistersTheClosureReturnedByGetHtmlBootstrap(): void
    {
        // No host adapter has booted in this test: the seam starts empty.
        $this->assertNull(InertiaFactory::getHtmlBootstrap());

        $bootstrap = static fn (array $page, string $json, string $attr): Response => new Response($json);

        InertiaFactory::setHtmlBootstrap($bootstrap);

        try {
            $this->assertSame($bootstrap, InertiaFactory::getHtmlBootstrap());
        } finally {
            // Clear the registered closure so sibling tests (which assert no
            // bootstrap is configured) are not contaminated by this one.
            (new ReflectionProperty(InertiaFactory::class, 'htmlBootstrap'))->setValue(null, null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function renderInertiaJson(InertiaResponse $response): array
    {
        return json_decode((string) $response->toResponse()->getContent(), true);
    }

    private function inertiaRequest(): Request
    {
        return Request::create('/dashboard', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
    }
}
