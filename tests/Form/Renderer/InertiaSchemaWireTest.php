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

use Middag\Framework\Form\Field\EntityPickerField;
use Middag\Framework\Form\Field\FileField;
use Middag\Framework\Form\Field\GenericField;
use Middag\Framework\Form\Field\IntField;
use Middag\Framework\Form\Field\SelectField;
use Middag\Framework\Form\Field\TextareaField;
use Middag\Framework\Form\Field\TextField;
use Middag\Framework\Form\Renderer\InertiaFieldMapper;
use Middag\Framework\Form\Renderer\InertiaRenderer;
use Middag\Ui\Block\Section;
use Middag\Ui\Form\FormState;
use Middag\Ui\Form\Group;
use Middag\Ui\Shared\Enum\ConditionOperator;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the exact JSON wire of the form_panel `props` (schema, values, errors,
 * meta) against golden fixtures captured from the renderer before any schema
 * node refactor. A byte difference means the React renderer contract changed.
 *
 * @internal
 */
#[CoversClass(InertiaRenderer::class)]
final class InertiaSchemaWireTest extends TestCase
{
    #[Test]
    #[DataProvider('formNames')]
    public function renderedPropsMatchGoldenWire(string $name): void
    {
        $form = new RendererFakeForm($this->schemaFor($name), new FormState(['title' => 'Hi'], ['title' => 'bad'], true));

        $json = json_encode(
            (new InertiaRenderer(new InertiaFieldMapper()))->render($form)->props,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $path = __DIR__ . '/golden/' . $name . '.json';
        if (getenv('MFW_CAPTURE_GOLDEN') === '1') {
            file_put_contents($path, $json . "\n");
        }

        self::assertStringEqualsFile($path, $json . "\n");
    }

    /** @return iterable<string, array{0: string}> */
    public static function formNames(): iterable
    {
        foreach (['flat_fields', 'nested_layout', 'validation_and_options', 'conditions', 'translatable_section', 'unlabelled_section'] as $name) {
            yield $name => [$name];
        }
    }

    /** @return array<int, mixed> */
    private function schemaFor(string $name): array
    {
        return match ($name) {
            'flat_fields' => [
                (new TextField('title'))->label('Title')->default('Untitled'),
                new TextareaField('body'),
                new GenericField('flag', FieldType::Checkbox),
            ],
            'nested_layout' => [
                Section::of('outer')->label('Outer')->fields(
                    new TextField('a'),
                    Group::of('row')->fields(
                        new TextField('b'),
                        Section::of('inner')->label('Inner')->fields(new IntField('c')),
                    ),
                ),
                Group::of('empty')->fields(),
                new TextField('loose'),
            ],
            'validation_and_options' => [
                (new TextField('code'))->required()->min(2)->max(8)->pattern('^[A-Z]+$')->placeholder('ph_code')->help('help_code'),
                (new TextareaField('notes'))->rows(5)->min(1)->max(500),
                (new IntField('qty'))->min(1)->max(10)->step(1)->readonly(),
                (new SelectField('kind'))->options(['a' => 'Alpha', 'b' => 'Beta']),
                (new SelectField('lazy'))->optionsFrom(static fn (): array => [['value' => 1, 'label' => 'One']]),
                (new FileField('doc'))->accept(['image/*', '.pdf'])->maxSize(1024),
                (new EntityPickerField('owner'))->displayField('name')->autocompleteHref('/api/users')->minChars(2),
            ],
            'conditions' => [
                (new TextField('type'))->default('x'),
                (new TextField('detail'))
                    ->visibleWhen('type', ConditionOperator::Eq, 'x')
                    ->hiddenWhen('type', ConditionOperator::In, ['y', 'z'])
                    ->requiredWhen('type', ConditionOperator::Neq, 3)
                    ->disabledWhen('type', ConditionOperator::NotIn, [1, 2]),
            ],
            'translatable_section' => [
                Section::of('t')->label(Translatable::of('sec_t', 'core'))->fields(new TextField('x')),
            ],
            'unlabelled_section' => [
                Section::of('u')->fields(new TextField('x')),
            ],
            default => [],
        };
    }
}
