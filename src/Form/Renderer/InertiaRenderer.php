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
use Middag\Ui\Form\Contract\FormSchemaNodeInterface;
use Middag\Ui\Form\FormFieldNode;
use Middag\Ui\Form\FormGroupNode;
use Middag\Ui\Form\FormSectionNode;
use Middag\Ui\Shared\Enum\FormComponent;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Shared\ValueObject\RendererOutput;
use Middag\Ui\Shared\ValueObject\Translatable;

/**
 * Inertia form renderer adapter for the `@middag-io/react` form_panel.
 *
 * Serializes the form schema into the props the lib's FormPanelBlock consumes.
 * Each schema item becomes a `FormSchemaNodeInterface` value object: a
 * `FormFieldNode` (props shaped by {@see InertiaFieldMapper}), a
 * `FormSectionNode`, or a `FormGroupNode`. `props['schema']` holds these objects;
 * their JSON serialization is the wire the React form_panel consumes.
 *
 * Produces RendererOutput::props() with shape:
 *   { schema: list<FormSchemaNodeInterface>, values: {...}, errors: {...}, meta: {} }
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
        return RenderTarget::Props;
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

        return RendererOutput::props(RenderTarget::Props, $props);
    }

    /**
     * Recursively build the schema node tree for the form_panel.
     *
     * @param array<int, FieldInterface|LayoutElementInterface> $items
     *
     * @return list<FormSchemaNodeInterface>
     */
    private function serializeSchema(array $items): array
    {
        $nodes = [];
        foreach ($items as $item) {
            if ($item instanceof Section) {
                $nodes[] = new FormSectionNode(
                    $item->id(),
                    $this->sectionLabel($item->labelData()),
                    $this->serializeSchema($item->children()),
                );
            } elseif ($item instanceof LayoutElementInterface) {
                // Groups and unknown layout elements serialize as a group node.
                $nodes[] = new FormGroupNode($item->id(), $this->serializeSchema($item->children()));
            } elseif ($item instanceof FieldInterface) {
                $body = $this->mapper->map($item->toDefinition());
                $nodes[] = new FormFieldNode($body['key'], FormComponent::from($body['component']), $body['props']);
            }
        }

        return $nodes;
    }

    /**
     * Rebuild a section label from its serialized form (Section exposes only
     * `labelData()`): a literal string, a Translatable payload or null.
     *
     * @param null|array<string, mixed>|string $data
     */
    private function sectionLabel(array|string|null $data): string|Translatable|null
    {
        if (!is_array($data)) {
            return $data;
        }

        return Translatable::of((string) $data['key'], (string) $data['domain'], (array) ($data['params'] ?? []));
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
