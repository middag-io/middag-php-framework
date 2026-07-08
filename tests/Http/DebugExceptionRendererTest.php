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

use Middag\Framework\Http\DebugExceptionRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(DebugExceptionRenderer::class)]
final class DebugExceptionRendererTest extends TestCase
{
    #[Test]
    public function rendersClassMessageLocationAndStack(): void
    {
        $throwable = new RuntimeException('boom detail');

        $html = DebugExceptionRenderer::html($throwable);

        $this->assertStringStartsWith('<!doctype html>', $html);
        $this->assertStringContainsString('500 — RuntimeException', $html);
        $this->assertStringContainsString('RuntimeException: boom detail', $html);
        $this->assertStringContainsString($throwable->getFile() . ':' . $throwable->getLine(), $html);
        $this->assertStringContainsString('<h2>Stack</h2>', $html);
    }

    #[Test]
    public function escapesHtmlInTheThrowableMessage(): void
    {
        $html = DebugExceptionRenderer::html(new RuntimeException('<script>alert(1)</script>'));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
