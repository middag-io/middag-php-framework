<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form\Renderer;

use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Ui\Condition\Condition;
use Middag\Ui\Form\FieldConstraints;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\ConditionOperator;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Guards the FieldDefinition -> `@middag-io/react` FormFieldNode contract: a
 * node-level `key`, the lowercase component wire key, a pre-resolved string
 * label, `[{value,label}]` options, discrete FormCondition props, and
 * type-split numeric/length bounds. The lib's FormField consumes this verbatim.
 *
 * @internal
 */
#[CoversClass(InertiaFieldMapper::class)]
final class InertiaFieldMapperTest extends TestCase
{
    #[Test]
    #[DataProvider('allFieldTypes')]
    public function everyFieldTypeMapsToItsLowercaseWireKey(FieldType $type): void
    {
        $mapped = (new InertiaFieldMapper())->map($this->def($type));

        self::assertArrayHasKey('component', $mapped);
        self::assertSame($type->value, $mapped['component']);
        self::assertArrayHasKey('props', $mapped);
    }

    /** @return iterable<string, array{FieldType}> */
    public static function allFieldTypes(): iterable
    {
        foreach (FieldType::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[Test]
    public function representativeFieldTypesMapToLowercaseWireKeys(): void
    {
        $mapper = new InertiaFieldMapper();

        self::assertSame('richtext', $mapper->map($this->def(FieldType::Richtext))['component']);
        self::assertSame('time', $mapper->map($this->def(FieldType::Time))['component']);
        self::assertSame('autocomplete', $mapper->map($this->def(FieldType::Autocomplete))['component']);
        self::assertSame('tags', $mapper->map($this->def(FieldType::Tags))['component']);
        // Distinct types the legacy PascalCase map collapsed onto a shared alias
        // (TextField, NumberField) now keep their own canonical wire key.
        self::assertSame('email', $mapper->map($this->def(FieldType::Email))['component']);
        self::assertSame('url', $mapper->map($this->def(FieldType::Url))['component']);
        self::assertSame('int', $mapper->map($this->def(FieldType::Int))['component']);
        self::assertSame('float', $mapper->map($this->def(FieldType::Float))['component']);
    }

    #[Test]
    public function uiZeroSixThreeFieldTypesMapToTheirWireKeys(): void
    {
        // ui 0.6.3 added slider/otp/native_select; each must reach the client
        // under its own registry key for the PHP->React form path.
        $mapper = new InertiaFieldMapper();

        self::assertSame('slider', $mapper->map($this->def(FieldType::Slider))['component']);
        self::assertSame('otp', $mapper->map($this->def(FieldType::Otp))['component']);
        self::assertSame('native_select', $mapper->map($this->def(FieldType::NativeSelect))['component']);
    }

    #[Test]
    public function nodeCarriesKeyFromFieldName(): void
    {
        // The lib reads identity from the node-level `key` (field.key), never
        // from props.name — so the mapper surfaces the field name as `key`.
        $mapped = (new InertiaFieldMapper())->map($this->def(FieldType::Text));

        self::assertSame('f', $mapped['key']);
        self::assertArrayNotHasKey('name', $mapped['props'], 'identity is the node key, not props.name');
    }

    #[Test]
    public function requiredPropComesFromConstraints(): void
    {
        $mapper = new InertiaFieldMapper();

        self::assertTrue(
            $mapper->map($this->def(FieldType::Text, new FieldConstraints(required: true)))['props']['required'],
        );
        self::assertFalse($mapper->map($this->def(FieldType::Text))['props']['required']);
    }

    #[Test]
    public function labelResolvesToAStringNotATranslatableObject(): void
    {
        // FieldField renders props.label as a raw string; a Translatable object
        // would break React. A literal label (empty-domain Translatable) resolves
        // to its key verbatim.
        $def = $this->def(FieldType::Text, label: Translatable::of('Title', ''));

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame('Title', $props['label']);
        self::assertIsString($props['label']);
    }

    #[Test]
    public function missingLabelFallsBackToEmptyString(): void
    {
        // FieldPropsBase.label is required (string), so a label-less field still
        // emits a string rather than null.
        $props = (new InertiaFieldMapper())->map($this->def(FieldType::Text))['props'];

        self::assertSame('', $props['label']);
    }

    #[Test]
    public function conditionsBecomeDiscreteFormConditionProps(): void
    {
        // The framework's conditions[] array becomes per-kind FormCondition props
        // ({field, operator, value}), with the operator vocabulary mapped to the
        // lib's closed union (eq -> equals).
        $def = $this->def(FieldType::Text, conditions: [
            new Condition('status', ConditionOperator::Eq, 'done', Condition::KIND_VISIBLE_WHEN),
            new Condition('status', ConditionOperator::Eq, 'done', Condition::KIND_REQUIRED_WHEN),
        ]);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame(['field' => 'status', 'operator' => 'equals', 'value' => 'done'], $props['visible_when']);
        self::assertSame(['field' => 'status', 'operator' => 'equals', 'value' => 'done'], $props['required_when']);
        self::assertArrayNotHasKey('conditions', $props, 'the raw conditions array must not leak');
    }

    #[Test]
    public function conditionOperatorVocabularyIsMappedToTheLibUnion(): void
    {
        $mapper = new InertiaFieldMapper();

        $op = fn (ConditionOperator $o): string => $mapper->map(
            $this->def(FieldType::Text, conditions: [new Condition('x', $o, 'v', Condition::KIND_VISIBLE_WHEN)]),
        )['props']['visible_when']['operator'];

        self::assertSame('equals', $op(ConditionOperator::Eq));
        self::assertSame('not_equals', $op(ConditionOperator::Neq));
        self::assertSame('in', $op(ConditionOperator::In));
        self::assertSame('not_in', $op(ConditionOperator::NotIn));
        // Operators with no client-form equivalent pass their raw wire value.
        self::assertSame('gt', $op(ConditionOperator::Gt));
    }

    #[Test]
    public function optionsMapBecomesValueLabelList(): void
    {
        $def = $this->def(FieldType::Select, options: ['low' => 'Low', 'high' => 'High']);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame(
            [['value' => 'low', 'label' => 'Low'], ['value' => 'high', 'label' => 'High']],
            $props['options'],
        );
    }

    #[Test]
    public function textLengthBoundsMapToValidation(): void
    {
        // On text-like fields min/max are string-length bounds → validation.*.
        $def = $this->def(FieldType::Text, attributes: ['min' => 2, 'max' => 200, 'pattern' => '\d+']);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame(2, $props['validation']['minLength']);
        self::assertSame(200, $props['validation']['maxLength']);
        self::assertSame('\d+', $props['validation']['pattern']);
        self::assertArrayNotHasKey('min', $props, 'length bounds must not leak as numeric props');
        self::assertArrayNotHasKey('max', $props);
    }

    #[Test]
    public function numericBoundsMapToTopLevelProps(): void
    {
        // On numeric fields min/max/step are numeric bounds → top-level props.
        $def = $this->def(FieldType::Int, attributes: ['min' => 0, 'max' => 100000, 'step' => 5]);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame(0, $props['min']);
        self::assertSame(100000, $props['max']);
        self::assertSame(5, $props['step']);
        self::assertArrayNotHasKey('validation', $props, 'numeric bounds are not length validation');
    }

    #[Test]
    public function textareaRowsLiftToProps(): void
    {
        $props = (new InertiaFieldMapper())->map($this->def(FieldType::Textarea, attributes: ['rows' => 4]))['props'];

        self::assertSame(4, $props['rows']);
    }

    #[Test]
    public function placeholderIntentResolvesToString(): void
    {
        // placeholder is stored as a {key, component} intent under attributes; it
        // surfaces as a top-level string prop.
        $def = $this->def(FieldType::Text, attributes: ['placeholder' => ['key' => 'What needs doing?', 'component' => '']]);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame('What needs doing?', $props['placeholder']);
    }

    #[Test]
    public function entityPickerLiftsDisplayField(): void
    {
        $def = $this->def(FieldType::EntityPicker, attributes: ['display_field' => 'title', 'source' => 'demo_tasks']);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame('title', $props['entityDisplayField']);
    }

    #[Test]
    public function optionsLoaderClosureIsResolvedToAValueLabelList(): void
    {
        // A Closure loader is not JSON-serializable: it must be resolved into the
        // `[{value,label}]` options list and never reach the props.
        $def = $this->def(
            FieldType::Select,
            attributes: ['options_loader' => static fn (): array => ['br' => 'Brasil', 'pt' => 'Portugal']],
        );

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame(
            [['value' => 'br', 'label' => 'Brasil'], ['value' => 'pt', 'label' => 'Portugal']],
            $props['options'],
        );
        self::assertArrayNotHasKey('attributes', $props, 'the raw attributes bag must not leak');
    }

    #[Test]
    public function propsAreJsonSerializableAndDoNotLeakServerOnlyAttributes(): void
    {
        // custom_rules carries server-only Symfony constraints that have no
        // client-form prop; none may reach the canonical props, which must
        // always JSON-encode cleanly.
        $def = $this->def(
            FieldType::Text,
            attributes: ['custom_rules' => [new Assert\Length(max: 5), new Assert\NotBlank()]],
        );

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertArrayNotHasKey('custom_rules', $props);
        self::assertArrayNotHasKey('attributes', $props);
        self::assertNotFalse(json_encode($props), 'props must be JSON-serializable (no Closures leak)');
    }

    #[Test]
    public function conditionValueIsCoercedToTheLibValueType(): void
    {
        $mapper = new InertiaFieldMapper();

        // scalar non-string → string
        $scalar = $mapper->map($this->def(FieldType::Int, conditions: [
            new Condition('age', ConditionOperator::Eq, 18, Condition::KIND_VISIBLE_WHEN),
        ]))['props']['visible_when'];
        self::assertSame('18', $scalar['value']);

        // list → list<string>
        $list = $mapper->map($this->def(FieldType::Text, conditions: [
            new Condition('role', ConditionOperator::In, [1, 2], Condition::KIND_VISIBLE_WHEN),
        ]))['props']['visible_when'];
        self::assertSame(['1', '2'], $list['value']);
    }

    #[Test]
    public function entityPickerLiftsAutocompleteHref(): void
    {
        $def = $this->def(FieldType::EntityPicker, attributes: [
            'autocomplete_href' => '/api/entities/tasks',
            'autocomplete_min_chars' => 3,
        ]);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame('/api/entities/tasks', $props['autocompleteHref']);
        self::assertSame(3, $props['autocompleteMinChars']);
    }

    #[Test]
    public function fileFieldAcceptListJoinsAndMaxSizeLifts(): void
    {
        // accept is a list (['image/*', '.pdf']) → a single HTML accept string;
        // max_size (bytes) → maxSize.
        $def = $this->def(FieldType::File, attributes: ['accept' => ['image/*', '.pdf'], 'max_size' => 1048576]);

        $props = (new InertiaFieldMapper())->map($def)['props'];

        self::assertSame('image/*,.pdf', $props['accept']);
        self::assertSame(1048576, $props['maxSize']);
    }

    /**
     * @param array<string, mixed>     $attributes
     * @param array<int|string, mixed> $options
     * @param array<int, Condition>    $conditions
     */
    #[Test]
    public function helpTextReadonlyAndValueLabelOptionsAreMapped(): void
    {
        $definition = new FieldDefinition(
            name: 'f',
            type: FieldType::Select,
            label: 'Choose',
            help: 'Pick one carefully',
            default: null,
            constraints: new FieldConstraints(),
            attributes: ['readonly' => true],
            conditions: [],
            options: [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']],
        );

        $props = (new InertiaFieldMapper())->map($definition)['props'];

        self::assertSame('Pick one carefully', $props['helpText']);
        self::assertTrue($props['readOnly']);
        self::assertSame(
            [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']],
            $props['options'],
        );
    }

    private function def(
        FieldType $type,
        FieldConstraints $constraints = new FieldConstraints(),
        array $attributes = [],
        array $options = [],
        array $conditions = [],
        string|Translatable|null $label = null,
    ): FieldDefinition {
        return new FieldDefinition(
            name: 'f',
            type: $type,
            label: $label,
            help: null,
            default: null,
            constraints: $constraints,
            attributes: $attributes,
            conditions: $conditions,
            options: $options,
        );
    }
}
