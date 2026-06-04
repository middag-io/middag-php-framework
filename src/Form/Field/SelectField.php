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

use Closure;
use Middag\Ui\Shared\Enum\FieldType;

/**
 * Single-value select dropdown field.
 *
 * @internal — use the FieldFactory
 */
final class SelectField extends AbstractField
{
    /**
     * Set static options as key => label pairs.
     *
     * @param array<int|string, string> $items
     */
    public function options(array $items): static
    {
        $this->options = $items;

        return $this;
    }

    /**
     * Defer option loading to a Closure evaluated at render time.
     */
    public function optionsFrom(Closure $loader): static
    {
        $this->attributes['options_loader'] = $loader;

        return $this;
    }

    public function searchable(bool $value = true): static
    {
        $this->attributes['searchable'] = $value;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::SELECT;
    }
}
