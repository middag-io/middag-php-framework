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
 * Duration picker field — seconds-based, host-agnostic.
 *
 * @internal — use the FieldFactory
 */
final class DurationField extends AbstractField
{
    /**
     * Allowed time units.
     *
     * @param array<int, int> $units List of unit sizes in seconds (e.g. 60 = minutes, 3600 = hours).
     */
    public function units(array $units): static
    {
        $this->attributes['units'] = $units;

        return $this;
    }

    /**
     * Default selected unit constant.
     */
    public function defaultUnit(int $unit): static
    {
        $this->attributes['default_unit'] = $unit;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::Duration;
    }
}
