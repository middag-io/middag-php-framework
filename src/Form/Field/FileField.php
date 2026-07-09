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
 * File upload field — host-agnostic storage descriptor.
 *
 * @internal — use the FieldFactory
 */
final class FileField extends AbstractField
{
    /**
     * Accepted MIME types or extensions (e.g. ['image/png', '.pdf']).
     *
     * @param array<int, string> $types
     */
    public function accept(array $types): static
    {
        $this->attributes['accept'] = $types;

        return $this;
    }

    /**
     * Maximum file size in bytes.
     */
    public function maxSize(int $bytes): static
    {
        $this->attributes['max_size'] = $bytes;

        return $this;
    }

    protected function defaultType(): FieldType
    {
        return FieldType::File;
    }
}
