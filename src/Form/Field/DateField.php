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
 * Date picker field (no time component).
 *
 * @internal — use the FieldFactory
 */
final class DateField extends AbstractField
{
    /**
     * Minimum selectable date (ISO 8601 string, e.g. '2026-01-01').
     */
    public function minDate(string $value): static
    {
        $this->attributes['min_date'] = $value;

        return $this;
    }

    /**
     * Maximum selectable date (ISO 8601 string).
     */
    public function maxDate(string $value): static
    {
        $this->attributes['max_date'] = $value;

        return $this;
    }

    /**
     * Whether the field value is optional (allows clearing the selection).
     */
    public function optional(bool $value = true): static
    {
        $this->attributes['optional'] = $value;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::Date;
    }
}
