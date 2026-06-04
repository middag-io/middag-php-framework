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
 * Radio button group field.
 *
 * @internal — use the FieldFactory
 */
final class RadioField extends AbstractField
{
    /**
     * Set options as key => label pairs.
     *
     * @param array<int|string, string> $items
     */
    public function options(array $items): static
    {
        $this->options = $items;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::RADIO;
    }
}
