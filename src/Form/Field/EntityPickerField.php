<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form\Field;

use Middag\Ui\Shared\Enum\FieldType;

/**
 * Async entity picker (autocomplete search against a registered source).
 *
 * @internal — use the FieldFactory
 */
final class EntityPickerField extends AbstractField
{
    /**
     * Registered source identifier (e.g. 'mycomponent/users').
     * Resolved by the host's entity-source resolver at render time.
     */
    public function source(string $source): static
    {
        $this->attributes['source'] = $source;
        $this->attributes['source_key'] = $source;

        return $this;
    }

    /**
     * Entity type key (resolved via EntityTypeRegistry).
     * Produces a default source from the type's entity class when no source_key is set.
     */
    public function typeKey(string $typeKey): static
    {
        $this->attributes['type_key'] = $typeKey;

        return $this;
    }

    /**
     * Entity field name used as display label in suggestions.
     */
    public function displayField(string $field): static
    {
        $this->attributes['display_field'] = $field;

        return $this;
    }

    /**
     * Entity field name used as the submitted value.
     */
    public function valueField(string $field): static
    {
        $this->attributes['value_field'] = $field;

        return $this;
    }

    /**
     * Async search endpoint URL the client autocompletes against.
     *
     * The {@see self::source()} key resolves entity data server-side; this URL is
     * what the client field fetches (with a `q` query param) for live search. The
     * host owns its routes, so the URL is supplied here rather than derived from
     * the source key. Without it the picker stays sync (static options only).
     */
    public function autocompleteHref(string $href): static
    {
        $this->attributes['autocomplete_href'] = $href;

        return $this;
    }

    /**
     * Minimum characters typed before the async search fires (client default: 2).
     */
    public function minChars(int $chars): static
    {
        $this->attributes['autocomplete_min_chars'] = $chars;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::ENTITY_PICKER;
    }
}
