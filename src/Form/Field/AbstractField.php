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
use Middag\Framework\Form\FormValidator;
use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Ui\Condition\Condition;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\FieldConstraints;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\ConditionOperator;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;
use Symfony\Component\Validator\Constraint;

/**
 * Base class for all field types.
 * Subclasses declare their FieldType() and may add type-specific attributes.
 *
 * @internal — modules use the FieldFactory
 */
abstract class AbstractField implements FieldInterface
{
    /** @var null|array{key: string, component: string} */
    protected ?array $label = null;

    /** @var null|array{key: string, component: string} */
    protected ?array $help = null;

    /** @var null|array{key: string, component: string} */
    protected ?array $placeholder = null;

    protected mixed $default = null;

    protected bool $required = false;

    protected bool $readonly = false;

    /** @var array<int, Condition> */
    protected array $conditions = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<int|string, mixed> */
    protected array $options = [];

    /** @var array<int, Constraint> */
    protected array $customRules = [];

    public function __construct(
        protected readonly string $name,
        protected readonly ?FieldType $explicitType = null,
    ) {
        $this->validateName($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    // --- Fluent setters (return static for chaining) ---

    /**
     * Set the field label as an i18n intent.
     *
     * `$key` is the translation key; `$component` is the host i18n domain it
     * belongs to. Both are stored verbatim and become a {@see Translatable}
     * (`key` + `domain`) via {@see self::toTranslatable()}. An empty `$component`
     * yields a Translatable with an empty domain; omitting label() entirely
     * leaves the label null (the field is rendered without one).
     */
    public function label(string $key, string $component = ''): static
    {
        $this->label = ['key' => $key, 'component' => $component];

        return $this;
    }

    /**
     * Set the field help text as an i18n intent.
     *
     * Same semantics as {@see self::label()}: `$key` is the translation key,
     * `$component` the host i18n domain. Resolves to a {@see Translatable}.
     */
    public function help(string $key, string $component = ''): static
    {
        $this->help = ['key' => $key, 'component' => $component];

        return $this;
    }

    /**
     * Set the field placeholder as an i18n intent.
     *
     * Same `$key` / `$component` (i18n domain) semantics as {@see self::label()}.
     * Unlike label/help this is not promoted to a {@see Translatable}; the raw
     * `{key, component}` intent is carried under `attributes['placeholder']`.
     */
    public function placeholder(string $key, string $component = ''): static
    {
        $this->placeholder = ['key' => $key, 'component' => $component];

        return $this;
    }

    /**
     * Set the field's initial value, surfaced as {@see FieldDefinition::$default}
     * (used to seed the rendered control / client props).
     */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Mark the field statically required.
     *
     * The flag travels in {@see FieldConstraints::$required} and is enforced
     * server-side by {@see FormValidator}, as well as
     * exposed to the renderer as a prop.
     */
    public function required(): static
    {
        $this->required = true;

        return $this;
    }

    /**
     * Mark the field read-only.
     *
     * Sets both the internal flag and `attributes['readonly']` so the renderer
     * receives it as a control attribute.
     */
    public function readonly(): static
    {
        $this->readonly = true;
        $this->attributes['readonly'] = true;

        return $this;
    }

    /**
     * Show this field only while `$field`'s value `$op`-compares to `$value`.
     *
     * Appends a {@see Condition::KIND_VISIBLE_WHEN} condition. Client-only: it is
     * serialized into the field's client props and is NOT enforced server-side by
     * {@see FormValidator}.
     */
    public function visibleWhen(string $field, ConditionOperator $op, mixed $value): static
    {
        $this->conditions[] = new Condition($field, $op, $value, Condition::KIND_VISIBLE_WHEN);

        return $this;
    }

    /**
     * Hide this field while `$field`'s value `$op`-compares to `$value`.
     *
     * Appends a {@see Condition::KIND_HIDDEN_WHEN} condition. Client-only: NOT
     * enforced server-side by {@see FormValidator}.
     */
    public function hiddenWhen(string $field, ConditionOperator $op, mixed $value): static
    {
        $this->conditions[] = new Condition($field, $op, $value, Condition::KIND_HIDDEN_WHEN);

        return $this;
    }

    /**
     * Require this field while `$field`'s value `$op`-compares to `$value`.
     *
     * Appends a {@see Condition::KIND_REQUIRED_WHEN} condition. This is the only
     * condition kind enforced server-side: {@see FormValidator}
     * treats the field as required when it evaluates true.
     */
    public function requiredWhen(string $field, ConditionOperator $op, mixed $value): static
    {
        $this->conditions[] = new Condition($field, $op, $value, Condition::KIND_REQUIRED_WHEN);

        return $this;
    }

    /**
     * Disable this field while `$field`'s value `$op`-compares to `$value`.
     *
     * Appends a {@see Condition::KIND_DISABLED_WHEN} condition. Client-only: NOT
     * enforced server-side by {@see FormValidator}.
     */
    public function disabledWhen(string $field, ConditionOperator $op, mixed $value): static
    {
        $this->conditions[] = new Condition($field, $op, $value, Condition::KIND_DISABLED_WHEN);

        return $this;
    }

    /**
     * Attach a custom Symfony constraint to the field.
     *
     * Constraints are carried under `attributes['custom_rules']` and enforced
     * server-side by {@see FormValidator}; they are NOT serialized to the client
     * renderer (client-side hints come from required/min/max/pattern). Accepts any
     * Symfony {@see Constraint}, e.g. `new Assert\Email()` or an `Assert\Callback`
     * wrapping bespoke logic.
     */
    public function rule(Constraint $rule): static
    {
        $this->customRules[] = $rule;

        return $this;
    }

    /**
     * Attach arbitrary metadata under `$key`, nested at `attributes['meta'][$key]`.
     *
     * NOTE: the Inertia/React renderer ({@see InertiaFieldMapper})
     * emits only the canonical FormFieldNode props and does NOT propagate this
     * `meta` bag to the client. It is available to host-side / server consumers
     * reading the {@see FieldDefinition} attributes directly; a custom renderer
     * may forward it explicitly.
     */
    public function meta(string $key, mixed $value): static
    {
        $this->attributes['meta'][$key] = $value;

        return $this;
    }

    // --- Output ---

    public function toDefinition(): FieldDefinition
    {
        return new FieldDefinition(
            name: $this->name,
            type: $this->fieldType(),
            label: $this->toTranslatable($this->label),
            help: $this->toTranslatable($this->help),
            default: $this->default,
            constraints: new FieldConstraints(required: $this->required),
            attributes: array_merge($this->attributes, [
                'placeholder' => $this->placeholder,
                'readonly' => $this->readonly,
                'custom_rules' => $this->customRules,
            ]),
            conditions: $this->conditions,
            options: $this->options,
        );
    }

    final protected function fieldType(): FieldType
    {
        return $this->explicitType ?? $this->defaultType();
    }

    abstract protected function defaultType(): FieldType;

    /**
     * Convert a stored {key, component} i18n intent into the ui Translatable VO.
     *
     * The fluent label()/help() carry a translation key plus the host
     * component; ui 0.5.0 models that as Translatable, where the old
     * `component` is the i18n `domain`. A null intent stays null (raw/no label).
     *
     * @param null|array{key: string, component: string} $intent
     */
    private function toTranslatable(?array $intent): ?Translatable
    {
        if ($intent === null) {
            return null;
        }

        return Translatable::of($intent['key'], $intent['component']);
    }

    // --- Validation ---

    private function validateName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Field name must be snake_case: ' . $name);
        }
        $reserved = ['id', 'submit', 'cancel', 'save', '_token'];
        if (in_array($name, $reserved, true)) {
            throw new InvalidArgumentException(sprintf("Field name '%s' is reserved by the framework", $name));
        }
    }
}
