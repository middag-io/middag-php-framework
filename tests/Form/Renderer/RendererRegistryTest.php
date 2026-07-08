<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form\Renderer;

use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Tests\Form\Renderer\Fixture\HtmlFormRenderer;
use Middag\Framework\Tests\Form\Renderer\Fixture\PropsFormRenderer;
use Middag\Ui\Shared\Enum\RenderTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The renderer registry indexes adapters by RenderTarget and resolves — or
 * rejects — a target on demand.
 *
 * @internal
 */
#[CoversClass(RendererRegistry::class)]
final class RendererRegistryTest extends TestCase
{
    #[Test]
    public function resolvesTheAdapterRegisteredForEachTarget(): void
    {
        $html = new HtmlFormRenderer();
        $props = new PropsFormRenderer();
        $registry = new RendererRegistry([$html, $props]);

        self::assertSame($html, $registry->get(RenderTarget::HTML));
        self::assertSame($props, $registry->get(RenderTarget::PROPS));
    }

    #[Test]
    public function lastAdapterWinsWhenTwoShareATarget(): void
    {
        $first = new HtmlFormRenderer();
        $second = new HtmlFormRenderer();
        $registry = new RendererRegistry([$first, $second]);

        self::assertSame($second, $registry->get(RenderTarget::HTML));
    }

    #[Test]
    public function throwsWhenNoAdapterIsRegisteredForATarget(): void
    {
        $registry = new RendererRegistry([new HtmlFormRenderer()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No form renderer for target: props');
        $registry->get(RenderTarget::PROPS);
    }
}
