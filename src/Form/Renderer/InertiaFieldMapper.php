<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Form\Renderer;

use Closure;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Data\Translatable;
use Middag\Ui\Shared\Enum\ConditionOperator;
use Middag\Ui\Shared\Enum\FieldType;

/**
 * Maps a FieldDefinition to a `@middag-io/react` FormFieldNode: a node-level
 * `key`, the client component key, and a canonical `props` object.
 *
 * The output is consumed verbatim by the lib's `FormField` (form_panel block),
 * so this mapper speaks that contract exactly:
 *   - identity is the node-level `key` (lib reads `field.key`), NOT `props.name`;
 *   - `label`/`helpText`/`placeholder` are pre-resolved strings (the form field
 *     layer does not resolve {@see Translatable} intents — see {@see self::resolveText()});
 *   - `options` is `[{value,label}]`, never an assoc map;
 *   - conditions become discrete `visible_when`/`hidden_when`/`required_when`/
 *     `disabled_when` `Middag\Ui\Condition\Condition` props shaped as the lib's FormCondition
 *     `{field, operator, value}` (operator vocabulary mapped);
 *   - length vs numeric bounds are split by field type (validation.* vs min/max/step).
 *
 * @internal
 */
final class InertiaFieldMapper
{
    /**
     * Map a FieldDefinition to a FormFieldNode body `{key, component, props}`.
     * The caller ({@see InertiaRenderer}) prepends `kind: "field"`.
     *
     * @return array{key: string, component: string, props: array<string, mixed>}
     */
    public function map(FieldDefinition $def): array
    {
        return [
            'key' => $def->name,
            'component' => $def->type->value,
            'props' => $this->buildProps($def),
        ];
    }

    /**
     * Build the canonical FieldPropsBase (+ type-specific) props.
     *
     * @return array<string, mixed>
     */
    private function buildProps(FieldDefinition $def): array
    {
        $attributes = $def->attributes;

        // Deferred option loader (SelectField::optionsFrom): resolve the Closure
        // at render time so the result can fold into `options`. A Closure is not
        // JSON-serializable; it is consumed here and never reaches the props.
        $options = $def->options;
        if (isset($attributes['options_loader']) && $attributes['options_loader'] instanceof Closure) {
            $loaded = ($attributes['options_loader'])();
            if (is_array($loaded)) {
                $options = $loaded;
            }
        }

        // label is required by the contract (FieldPropsBase.label: string); fall
        // back to an empty literal when a field declares none.
        $props = [
            'label' => $this->resolveText($def->label) ?? '',
            'required' => $def->constraints->required,
        ];

        $placeholder = $this->resolveIntent($attributes['placeholder'] ?? null);
        if ($placeholder !== null && $placeholder !== '') {
            $props['placeholder'] = $placeholder;
        }

        $helpText = $this->resolveText($def->help);
        if ($helpText !== null && $helpText !== '') {
            $props['helpText'] = $helpText;
        }

        if (($attributes['readonly'] ?? false) === true) {
            $props['readOnly'] = true;
        }

        $optionList = $this->normalizeOptions($options);
        if ($optionList !== []) {
            $props['options'] = $optionList;
        }

        // Conditions → discrete typed props. Condition::KIND_* already equals the
        // lib prop names (visible_when/hidden_when/required_when/disabled_when),
        // so the kind is the prop key. The lib's FormCondition holds a single rule
        // per kind; if a field declares several of one kind, the last one wins.
        foreach ($def->conditions as $condition) {
            $props[$condition->kind] = [
                'field' => $condition->field,
                'operator' => $this->mapOperator($condition->operator),
                'value' => $this->conditionValue($condition->value),
            ];
        }

        $this->applyTypeSpecificProps($props, $def->type, $attributes);

        return $props;
    }

    /**
     * Resolve a label/help value to a wire string.
     *
     * Form field labels in the React contract are pre-resolved strings. A literal
     * label (`->label('Title')`, an empty-domain {@see Translatable}) yields its
     * key verbatim, which IS the literal. A field-label carrying a real i18n
     * domain cannot be resolved here (the mapper has no translator) and falls
     * back to the key; hosts needing translated form labels resolve them
     * server-side before the field reaches this mapper.
     */
    private function resolveText(string|Translatable|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return $value->key;
    }

    /**
     * Resolve a `{key, component}` i18n intent (e.g. placeholder) to a string.
     * Same literal-vs-intent rule as {@see self::resolveText()}.
     *
     * @param null|array{key?: string, component?: string} $intent
     */
    private function resolveIntent(?array $intent): ?string
    {
        if ($intent === null) {
            return null;
        }

        return isset($intent['key']) ? (string) $intent['key'] : null;
    }

    /**
     * Map the framework's condition operator to the lib's FormCondition operator.
     *
     * The React FormCondition union expresses only equality and set membership
     * (`equals`/`not_equals`/`in`/`not_in`). Richer framework operators
     * (gt/lt/truthy/matches/...) have no client-form equivalent; the raw wire
     * value is passed through so nothing is silently dropped — the client
     * evaluator ignores operators it cannot express.
     */
    private function mapOperator(ConditionOperator $operator): string
    {
        return match ($operator) {
            ConditionOperator::EQ => 'equals',
            ConditionOperator::NEQ => 'not_equals',
            ConditionOperator::IN => 'in',
            ConditionOperator::NOT_IN => 'not_in',
            default => $operator->value,
        };
    }

    /**
     * Coerce a condition value to the lib's FormCondition value type
     * (`string | string[]`). Scalars stringify; a list maps element-wise; null
     * becomes an empty string. The framework Condition holds a `mixed` value, but
     * the wire contract is string-typed (the client compares stringly).
     *
     * @return list<string>|string
     */
    private function conditionValue(mixed $value): array|string
    {
        if (is_array($value)) {
            return array_values(array_map(static fn (mixed $v): string => (string) $v, $value));
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * Convert options to the lib's `[{value, label}]` list.
     *
     * Accepts the fluent DSL's assoc map (`value => label`) and passes through an
     * already-canonical `[{value, label}]` list. `value` is coerced to string
     * (the contract types option values as strings).
     *
     * @param array<int|string, mixed> $options
     *
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(array $options): array
    {
        if ($options === []) {
            return [];
        }

        $out = [];
        foreach ($options as $key => $value) {
            if (is_array($value) && isset($value['value'], $value['label'])) {
                $out[] = ['value' => (string) $value['value'], 'label' => (string) $value['label']];

                continue;
            }

            $out[] = ['value' => (string) $key, 'label' => (string) $value];
        }

        return $out;
    }

    /**
     * Lift type-specific attributes to the typed top-level props the lib reads.
     *
     * The fluent `min()`/`max()` mean string length on text-like fields (→
     * `validation.minLength`/`maxLength`) but numeric bounds on numeric fields (→
     * `props.min`/`max`). `rows` (textarea), `step` (numeric) and entity display
     * field are lifted likewise.
     *
     * @param array<string, mixed> $props      mutated in place
     * @param array<string, mixed> $attributes
     */
    private function applyTypeSpecificProps(array &$props, FieldType $type, array $attributes): void
    {
        $validation = [];

        switch ($type) {
            case FieldType::TEXT:
            case FieldType::EMAIL:
            case FieldType::URL:
            case FieldType::PASSWORD:
            case FieldType::TEXTAREA:
                if (isset($attributes['min'])) {
                    $validation['minLength'] = (int) $attributes['min'];
                }
                if (isset($attributes['max'])) {
                    $validation['maxLength'] = (int) $attributes['max'];
                }
                if (isset($attributes['pattern'])) {
                    $validation['pattern'] = (string) $attributes['pattern'];
                }
                if ($type === FieldType::TEXTAREA && isset($attributes['rows'])) {
                    $props['rows'] = (int) $attributes['rows'];
                }

                break;

            case FieldType::INT:
            case FieldType::FLOAT:
            case FieldType::SLIDER:
                if (isset($attributes['min'])) {
                    $props['min'] = $attributes['min'];
                }
                if (isset($attributes['max'])) {
                    $props['max'] = $attributes['max'];
                }
                if (isset($attributes['step'])) {
                    $props['step'] = $attributes['step'];
                }

                break;

            case FieldType::ENTITY_PICKER:
                if (isset($attributes['display_field'])) {
                    $props['entityDisplayField'] = (string) $attributes['display_field'];
                }
                // `autocompleteHref` is the host-supplied search endpoint URL (the
                // field carries a `source` key for server-side resolution AND the
                // URL the client autocompletes against). Without it the lib's
                // entity_picker stays sync (static `options` only).
                if (isset($attributes['autocomplete_href'])) {
                    $props['autocompleteHref'] = (string) $attributes['autocomplete_href'];
                }
                if (isset($attributes['autocomplete_min_chars'])) {
                    $props['autocompleteMinChars'] = (int) $attributes['autocomplete_min_chars'];
                }

                break;

            case FieldType::FILE:
                // accept is stored as a list (e.g. ['image/*', '.pdf']) but the lib
                // wants a single HTML accept string; max_size (bytes) → maxSize.
                if (isset($attributes['accept']) && is_array($attributes['accept']) && $attributes['accept'] !== []) {
                    $props['accept'] = implode(',', array_map(static fn (mixed $a): string => (string) $a, $attributes['accept']));
                }
                if (isset($attributes['max_size'])) {
                    $props['maxSize'] = (int) $attributes['max_size'];
                }

                break;

            default:
                break;
        }

        if ($validation !== []) {
            $props['validation'] = $validation;
        }
    }
}
