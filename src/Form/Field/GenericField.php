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

use InvalidArgumentException;
use Middag\Ui\Shared\Enum\FieldType;

/**
 * Generic field for FieldType variants without type-specific behavior
 * (checkbox, switch, hidden, etc.).
 *
 * Requires explicit FieldType because no default exists.
 *
 * @internal — use the FieldFactory
 */
final class GenericField extends AbstractField
{
    public function __construct(string $name, FieldType $type)
    {
        parent::__construct($name, $type);
    }

    protected function defaultType(): FieldType
    {
        throw new InvalidArgumentException('GenericField requires explicit FieldType via constructor.');
    }
}
