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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The UI-contract → Inertia bridge. A middag-io/ui contract envelope is
 * returned as an Inertia response with the envelope folded into a reserved
 * `contract` prop and the component derived from the envelope kind.
 *
 * @internal
 */
#[CoversNothing]
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
