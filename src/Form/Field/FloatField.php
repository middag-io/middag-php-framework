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
 * Float numeric input field.
 *
 * @internal — use the FieldFactory
 */
final class FloatField extends AbstractField
{
    public function max(float $value): static
    {
        $this->attributes['max'] = $value;

        return $this;
    }

    public function min(float $value): static
    {
        $this->attributes['min'] = $value;

        return $this;
    }

    public function step(float $value): static
    {
        $this->attributes['step'] = $value;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::FLOAT;
    }
}
