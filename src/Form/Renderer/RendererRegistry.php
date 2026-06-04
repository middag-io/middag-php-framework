<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form\Renderer;

use Middag\Ui\Form\Contract\FormRendererInterface;
use Middag\Ui\Shared\Enum\RenderTarget;
use RuntimeException;

/**
 * Registry of form renderer adapters indexed by RenderTarget.
 *
 * Created by the DI container with tagged renderer services (the framework
 * auto-registers a default Inertia pipeline; hosts may override).
 * Resolves the `FormRendererInterface` adapter for a given `RenderTarget` so a controller can render a form to its output.
 *
 * Host-facing: a controller resolves the renderer for a target and renders a
 * form to its output, so this is part of the supported integration surface.
 * Concrete renderer adapters remain @internal.
 *
 * @api
 */
final readonly class RendererRegistry
{
    /** @var array<string, FormRendererInterface> */
    private array $byTarget;

    /** @param iterable<FormRendererInterface> $renderers */
    public function __construct(iterable $renderers)
    {
        $map = [];
        foreach ($renderers as $r) {
            $map[$r::target()->value] = $r;
        }
        $this->byTarget = $map;
    }

    /**
     * Resolve the adapter for the given render target.
     *
     * @throws RuntimeException when no adapter is registered for the target
     */
    public function get(RenderTarget $target): FormRendererInterface
    {
        return $this->byTarget[$target->value]
            ?? throw new RuntimeException('No form renderer for target: ' . $target->value);
    }
}
