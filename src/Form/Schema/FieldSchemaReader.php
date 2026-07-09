<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form\Schema;

use Middag\Framework\Form\Attribute\Field;
use Middag\Framework\Form\Field\GenericField;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Shared\Enum\FieldType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Builds a form field schema from a class's `#[Field]` properties.
 *
 * Call {@see self::read()} from an `AbstractForm::schema()` to declare fields on
 * a DTO instead of hand-building them with the `FieldFactory`. Each annotated
 * property becomes a field whose name is the property name in snake_case, with
 * the type taken from the attribute or inferred from the property's PHP type.
 * Results are cached per class, so reflection runs once.
 *
 * @api
 */
final class FieldSchemaReader
{
    /** @var array<class-string, list<FieldInterface>> */
    private array $cache = [];

    /**
     * @param class-string $class
     *
     * @return list<FieldInterface>
     */
    public function read(string $class): array
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $fields = [];

        foreach ((new ReflectionClass($class))->getProperties() as $property) {
            $attributes = $property->getAttributes(Field::class);

            if ($attributes === []) {
                continue;
            }

            $fields[] = $this->build($property, $attributes[0]->newInstance());
        }

        return $this->cache[$class] = $fields;
    }

    private function build(ReflectionProperty $property, Field $field): FieldInterface
    {
        $instance = new GenericField(
            $this->toSnakeCase($property->getName()),
            $field->type ?? $this->inferType($property),
        );

        if ($field->label !== null) {
            $instance->label($field->label, $field->domain);
        }

        if ($field->help !== null) {
            $instance->help($field->help, $field->domain);
        }

        if ($field->placeholder !== null) {
            $instance->placeholder($field->placeholder, $field->domain);
        }

        if ($field->default !== null) {
            $instance->default($field->default);
        }

        if ($field->required) {
            $instance->required();
        }

        if ($field->readonly) {
            $instance->readonly();
        }

        return $instance;
    }

    private function inferType(ReflectionProperty $property): FieldType
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            return FieldType::Text;
        }

        return match ($type->getName()) {
            'int' => FieldType::Int,
            'float' => FieldType::Float,
            'bool' => FieldType::Checkbox,
            'DateTimeInterface', 'DateTime', 'DateTimeImmutable' => FieldType::Date,
            default => FieldType::Text,
        };
    }

    private function toSnakeCase(string $name): string
    {
        $snake = preg_replace('/[A-Z]/', '_$0', $name) ?? $name;

        return strtolower(ltrim($snake, '_'));
    }
}
