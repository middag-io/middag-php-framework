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
 * Single-line text input field.
 *
 * @internal — use the FieldFactory
 */
final class TextField extends AbstractField
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

    public function pattern(string $value): static
    {
        $this->attributes['pattern'] = $value;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::Text;
    }
}
