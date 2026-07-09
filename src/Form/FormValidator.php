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

use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\SymfonyTranslatorAdapter;
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Condition\Condition;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates form values against a schema of fields using the Symfony Validator.
 *
 * Walks the schema (flattening layout elements) and, for each field, derives the
 * Symfony constraints from the typed `FieldConstraints` and the untyped attribute
 * bag: required (NotBlank, static or condition-based), max/min/pattern
 * (interpreted by field type — numeric value vs string length), plus any custom
 * Symfony constraint attached via `AbstractField::rule()`.
 *
 * An optional field with an empty value is skipped entirely, mirroring Symfony's
 * own treatment of null/empty for length and regex constraints and keeping
 * comparison constraints from firing on an empty string.
 *
 * Returns per-field errors: a string when there is exactly one error, or a
 * string[] when several constraints are violated.
 *
 * @internal
 */
final readonly class FormValidator
{
    public function __construct(
        private ConditionEvaluator $evaluator,
        private ?TranslatorInterface $translator = null,
    ) {}

    /**
     * Validate the given values against the schema.
     *
     * @param array<int, FieldInterface|LayoutElementInterface> $schema
     * @param array<string, mixed>                              $values
     *
     * @return array<string, string|string[]>
     */
    public function validate(array $schema, array $values): array
    {
        $errors = [];
        $validator = $this->buildValidator();

        foreach ($this->flattenFields($schema) as $field) {
            $def = $field->toDefinition();
            $value = $values[$def->name] ?? null;
            $isRequired = $this->isFieldRequired($def, $values);

            // Optional + empty: nothing to validate.
            if (!$isRequired && ($value === null || $value === '')) {
                continue;
            }

            $constraints = $this->constraintsFor($def, $isRequired);

            if ($constraints === []) {
                continue;
            }

            $violations = $validator->validate($value, $constraints);

            if (count($violations) === 0) {
                continue;
            }

            $messages = [];

            foreach ($violations as $violation) {
                $messages[] = (string) $violation->getMessage();
            }

            $errors[$def->name] = count($messages) === 1 ? $messages[0] : $messages;
        }

        return $errors;
    }

    /**
     * Build the Symfony validator, routing violation messages through the host
     * translator (domain `validators`) when one is configured. Without a
     * translator the default English messages are returned unchanged.
     */
    private function buildValidator(): ValidatorInterface
    {
        if (!$this->translator instanceof TranslatorInterface) {
            return Validation::createValidator();
        }

        return Validation::createValidatorBuilder()
            ->setTranslator(new SymfonyTranslatorAdapter($this->translator))
            ->setTranslationDomain('validators')
            ->getValidator();
    }

    /**
     * Determine whether a field is required given the current form values.
     *
     * A field is required when its `FieldConstraints::$required` is true, or when
     * any attached KIND_REQUIRED_WHEN condition evaluates to true.
     *
     * @param array<string, mixed> $values
     */
    private function isFieldRequired(FieldDefinition $def, array $values): bool
    {
        if ($def->constraints->required) {
            return true;
        }

        foreach ($def->conditions as $cond) {
            if ($cond->kind === Condition::KIND_REQUIRED_WHEN && $this->evaluator->evaluate($cond, $values)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the Symfony constraints for a field.
     *
     * `max`/`min` bound the numeric value for INT/FLOAT fields and the string
     * length for every other type; `pattern` applies to non-numeric fields only.
     * Custom Symfony constraints carried in `attributes['custom_rules']` are
     * appended verbatim.
     *
     * @return list<Constraint>
     */
    private function constraintsFor(FieldDefinition $def, bool $isRequired): array
    {
        $constraints = [];

        if ($isRequired) {
            $constraints[] = new Assert\NotBlank();
        }

        $isNumericField = in_array($def->type, [FieldType::Int, FieldType::Float], true);
        $attributes = $def->attributes;

        if (isset($attributes['max'])) {
            $constraints[] = $isNumericField
                ? new Assert\LessThanOrEqual($attributes['max'])
                : new Assert\Length(max: (int) $attributes['max']);
        }

        if (isset($attributes['min'])) {
            $constraints[] = $isNumericField
                ? new Assert\GreaterThanOrEqual($attributes['min'])
                : new Assert\Length(min: (int) $attributes['min']);
        }

        if (!$isNumericField && isset($attributes['pattern'])) {
            $constraints[] = new Assert\Regex((string) $attributes['pattern']);
        }

        foreach ($this->customConstraints($attributes) as $constraint) {
            $constraints[] = $constraint;
        }

        return $constraints;
    }

    /**
     * Extract the Symfony constraints attached as custom rules.
     *
     * @param array<string, mixed> $attributes
     *
     * @return list<Constraint>
     */
    private function customConstraints(array $attributes): array
    {
        $rules = $attributes['custom_rules'] ?? [];

        if (!is_array($rules)) {
            return [];
        }

        return array_values(array_filter(
            $rules,
            static fn (mixed $rule): bool => $rule instanceof Constraint,
        ));
    }

    /**
     * Recursively flatten schema items into a list of fields only.
     *
     * @param array<int, FieldInterface|LayoutElementInterface> $items
     *
     * @return array<int, FieldInterface>
     */
    private function flattenFields(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if ($item instanceof FieldInterface) {
                $out[] = $item;
            } elseif ($item instanceof LayoutElementInterface) {
                array_push($out, ...$this->flattenFields($item->children()));
            }
        }

        return $out;
    }
}
