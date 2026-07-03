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

use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Block\Section;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\Contract\FormRendererInterface;
use Middag\Ui\Form\Group;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Shared\ValueObject\RendererOutput;

/**
 * Inertia form renderer adapter for the `@middag-io/react` form_panel.
 *
 * Serializes the form schema into the props the lib's FormPanelBlock consumes.
 * Each schema item becomes a typed node: a field (`{kind, key, component, props}`
 * shaped by {@see InertiaFieldMapper}), a section, or a group.
 *
 * Produces RendererOutput::props() with shape:
 *   { schema: [...], values: {...}, errors: {...}, meta: {} }
 * where `values` merges field defaults (so a fresh form renders its defaults)
 * under any submitted/hydrated state.
 *
 * @internal
 */
final readonly class InertiaRenderer implements FormRendererInterface
{
    public function __construct(private InertiaFieldMapper $mapper) {}

    /** {@inheritdoc} */
    public static function target(): RenderTarget
    {
        return RenderTarget::PROPS;
    }

    /** {@inheritdoc} */
    public function render(FormInterface $form): RendererOutput
    {
        $state = $form->state();
        $items = $form->schema();
        $schema = $this->serializeSchema($items);

        $props = [
            'schema' => $schema,
            // The lib binds the form to `values` (keyed by field key), not to a
            // per-field default prop — so field defaults are seeded here and a
            // fresh (unsubmitted) form renders them. Submitted/hydrated state wins.
            'values' => array_merge($this->collectDefaults($items), $state->values()),
            'errors' => $state->errors(),
            'meta' => [],
        ];

        return RendererOutput::props(RenderTarget::PROPS, $props);
    }

    /**
     * Recursively serialize a schema array into serializable nodes.
     *
     * @param array<int, FieldInterface|LayoutElementInterface> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeSchema(array $items): array
    {
        $nodes = [];
        foreach ($items as $item) {
            if ($item instanceof Section) {
                $nodes[] = [
                    'kind' => 'section',
                    'id' => $item->id(),
                    'label' => $item->labelData(),
                    'children' => $this->serializeSchema($item->children()),
                ];
            } elseif ($item instanceof Group) {
                $nodes[] = [
                    'kind' => 'group',
                    'id' => $item->id(),
                    'children' => $this->serializeSchema($item->children()),
                ];
            } elseif ($item instanceof LayoutElementInterface) {
                // Unknown layout element — serialize as generic group-like node.
                $nodes[] = [
                    'kind' => 'group',
                    'id' => $item->id(),
                    'children' => $this->serializeSchema($item->children()),
                ];
            } elseif ($item instanceof FieldInterface) {
                // map() returns the FormFieldNode body {key, component, props};
                // prepend the node discriminator.
                $nodes[] = ['kind' => 'field'] + $this->mapper->map($item->toDefinition());
            }
        }

        return $nodes;
    }

    /**
     * Collect non-null field defaults keyed by field name, recursing into layout
     * containers (sections/groups). These seed the form's initial `values`.
     *
     * @param array<int, FieldInterface|LayoutElementInterface> $items
     *
     * @return array<string, mixed>
     */
    private function collectDefaults(array $items): array
    {
        $defaults = [];
        foreach ($items as $item) {
            if ($item instanceof FieldInterface) {
                $def = $item->toDefinition();
                if ($def->default !== null) {
                    $defaults[$def->name] = $def->default;
                }
            } elseif ($item instanceof LayoutElementInterface) {
                $defaults = array_merge($defaults, $this->collectDefaults($item->children()));
            }
        }

        return $defaults;
    }
}
