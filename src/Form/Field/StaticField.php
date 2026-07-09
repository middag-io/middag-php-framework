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
 * Static display-only field (renders a read-only string, no form submission).
 *
 * @internal — use the FieldFactory
 */
final class StaticField extends AbstractField
{
    /**
     * Set the display content as an i18n intent: $key is the translation key,
     * $component the host i18n domain.
     */
    public function content(string $key, string $component = ''): static
    {
        $this->attributes['content'] = ['key' => $key, 'component' => $component];

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::Static;
    }
}
