<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form\Renderer\Fixture;

use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\Contract\FormRendererInterface;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Shared\ValueObject\RendererOutput;

/**
 * Test renderer adapter registered under the HTML render target.
 *
 * @internal
 */
final class HtmlFormRenderer implements FormRendererInterface
{
    public static function target(): RenderTarget
    {
        return RenderTarget::HTML;
    }

    public function render(FormInterface $form): RendererOutput
    {
        return RendererOutput::html(RenderTarget::HTML, '<form></form>');
    }
}
