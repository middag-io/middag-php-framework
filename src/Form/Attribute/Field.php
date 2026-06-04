<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form\Attribute;

use Attribute;
use Middag\Framework\Form\Schema\FieldSchemaReader;
use Middag\Ui\Shared\Enum\FieldType;

/**
 * Declares a form field on a DTO/model property.
 *
 * A {@see FieldSchemaReader} reflects the
 * annotated properties into the same `FieldInterface[]` an `AbstractForm::schema()`
 * returns — declarative fields instead of the imperative `FieldFactory` calls.
 * When `$type` is null the field type is inferred from the property's PHP type
 * (string → TEXT, int → INT, float → FLOAT, bool → CHECKBOX,
 * DateTimeInterface → DATE, otherwise TEXT).
 *
 * Server-side validation stays on `#[Assert\*]` (the validator already reads
 * Symfony constraints); this attribute only declares the field/widget shape.
 * It covers the declarative-scalar subset — options, conditions and entity
 * pickers still use the fluent `schema()` path, which this coexists with.
 *
 * `$label`, `$help` and `$placeholder` are i18n keys resolved under `$domain`.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    public function __construct(
        public ?FieldType $type = null,
        public ?string $label = null,
        public ?string $help = null,
        public ?string $placeholder = null,
        public string $domain = '',
        public mixed $default = null,
        public bool $required = false,
        public bool $readonly = false,
    ) {}
}
