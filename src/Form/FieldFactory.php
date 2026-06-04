<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form;

use Middag\Framework\Form\Field\DateField;
use Middag\Framework\Form\Field\DurationField;
use Middag\Framework\Form\Field\EntityPickerField;
use Middag\Framework\Form\Field\FileField;
use Middag\Framework\Form\Field\FloatField;
use Middag\Framework\Form\Field\GenericField;
use Middag\Framework\Form\Field\IntField;
use Middag\Framework\Form\Field\RadioField;
use Middag\Framework\Form\Field\SelectField;
use Middag\Framework\Form\Field\StaticField;
use Middag\Framework\Form\Field\TextareaField;
use Middag\Framework\Form\Field\TextField;
use Middag\Ui\Shared\Enum\FieldType;

/**
 * Static factory for all form field types.
 *
 * Each method returns a concrete field instance pre-configured with the
 * given name. Concrete field classes are @internal; this class is the
 * public-facing entry point for form building.
 *
 * Variants of base types (email/password/url, datetime, multiselect, header)
 * reuse the base class with an explicit FieldType — no per-variant class.
 *
 * Every factory takes a snake_case $name (validated by AbstractField against
 * `/^[a-z][a-z0-9_]*$/`); the reserved names id/submit/cancel/save/_token throw
 * {@see \InvalidArgumentException}.
 *
 * @api
 */
final class FieldFactory
{
    private function __construct() {}

    public static function text(string $name): TextField
    {
        return new TextField($name);
    }

    public static function textarea(string $name): TextareaField
    {
        return new TextareaField($name);
    }

    public static function email(string $name): TextField
    {
        return new TextField($name, FieldType::EMAIL);
    }

    public static function password(string $name): TextField
    {
        return new TextField($name, FieldType::PASSWORD);
    }

    public static function url(string $name): TextField
    {
        return new TextField($name, FieldType::URL);
    }

    public static function integer(string $name): IntField
    {
        return new IntField($name);
    }

    public static function decimal(string $name): FloatField
    {
        return new FloatField($name);
    }

    public static function checkbox(string $name): GenericField
    {
        return new GenericField($name, FieldType::CHECKBOX);
    }

    public static function toggle(string $name): GenericField
    {
        return new GenericField($name, FieldType::SWITCH);
    }

    public static function date(string $name): DateField
    {
        return new DateField($name);
    }

    public static function datetime(string $name): DateField
    {
        return new DateField($name, FieldType::DATETIME);
    }

    public static function duration(string $name): DurationField
    {
        return new DurationField($name);
    }

    public static function entityPicker(string $name): EntityPickerField
    {
        return new EntityPickerField($name);
    }

    public static function file(string $name): FileField
    {
        return new FileField($name);
    }

    public static function header(string $name): StaticField
    {
        return new StaticField($name, FieldType::HEADER);
    }

    public static function hidden(string $name): GenericField
    {
        return new GenericField($name, FieldType::HIDDEN);
    }

    public static function multiselect(string $name): SelectField
    {
        return new SelectField($name, FieldType::MULTISELECT);
    }

    public static function radio(string $name): RadioField
    {
        return new RadioField($name);
    }

    public static function select(string $name): SelectField
    {
        return new SelectField($name);
    }

    public static function display(string $name): StaticField
    {
        return new StaticField($name);
    }
}
