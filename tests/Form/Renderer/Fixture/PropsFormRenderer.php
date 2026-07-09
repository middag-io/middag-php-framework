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
 * Test renderer adapter registered under the PROPS render target.
 *
 * @internal
 */
final class PropsFormRenderer implements FormRendererInterface
{
    public static function target(): RenderTarget
    {
        return RenderTarget::Props;
    }

    public function render(FormInterface $form): RendererOutput
    {
        return RendererOutput::props(RenderTarget::Props, ['fields' => []]);
    }
}
