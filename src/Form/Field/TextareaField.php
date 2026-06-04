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
 * Multi-line text area field.
 *
 * @internal — use the FieldFactory
 */
final class TextareaField extends AbstractField
{
    public function max(int $value): static
    {
        $this->attributes['max'] = $value;

        return $this;
    }

    public function min(int $value): static
    {
        $this->attributes['min'] = $value;

        return $this;
    }

    public function rows(int $value): static
    {
        $this->attributes['rows'] = $value;

        return $this;
    }

    public function cols(int $value): static
    {
        $this->attributes['cols'] = $value;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::TEXTAREA;
    }
}
