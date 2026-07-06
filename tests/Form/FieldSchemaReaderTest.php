<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Form;

use DateTimeImmutable;
use Middag\Framework\Form\Attribute\Field;
use Middag\Framework\Form\Schema\FieldSchemaReader;
use Middag\Framework\Kernel\ServiceProvider;
use Middag\Framework\Tests\Form\Fixture\ProfileDto;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * #[Field] turns DTO properties into a form field schema: name in snake_case,
 * type explicit or inferred, only annotated properties included.
 *
 * @internal
 */
#[CoversClass(FieldSchemaReader::class)]
#[CoversClass(Field::class)]
#[CoversClass(ServiceProvider::class)]
final class FieldSchemaReaderTest extends TestCase
{
    public function testBuildsSchemaFromAnnotatedProperties(): void
    {
        $fields = (new FieldSchemaReader())->read(ProfileDto::class);

        $definitions = [];
        foreach ($fields as $field) {
            $definition = $field->toDefinition();
            $definitions[$definition->name] = $definition;
        }

        // Only annotated properties, names mapped to snake_case, declaration order.
        self::assertSame(['full_name', 'email', 'age', 'active'], array_keys($definitions));
        self::assertArrayNotHasKey('internal', $definitions);
    }

    public function testTypeIsExplicitOrInferredFromThePropertyType(): void
    {
        $definitions = $this->definitionsByName();

        self::assertSame(FieldType::TEXT, $definitions['full_name']->type);   // inferred: string
        self::assertSame(FieldType::EMAIL, $definitions['email']->type);      // explicit
        self::assertSame(FieldType::INT, $definitions['age']->type);          // inferred: int
        self::assertSame(FieldType::CHECKBOX, $definitions['active']->type);  // inferred: bool
    }

    public function testSettersFromTheAttributeAreApplied(): void
    {
        $definitions = $this->definitionsByName();

        self::assertInstanceOf(Translatable::class, $definitions['full_name']->label);
        self::assertTrue($definitions['full_name']->constraints->required);
        self::assertFalse($definitions['email']->constraints->required);
    }

    public function testWiredContainerResolvesTheReader(): void
    {
        $container = new ContainerBuilder();
        ServiceProvider::register($container, __DIR__);
        $container->compile();

        self::assertInstanceOf(FieldSchemaReader::class, $container->get(FieldSchemaReader::class));
    }

    public function testReadIsCachedPerClass(): void
    {
        $reader = new FieldSchemaReader();

        // Second read returns the exact same materialised list (reflection runs once).
        self::assertSame($reader->read(ProfileDto::class), $reader->read(ProfileDto::class));
    }

    public function testEveryAttributeSetterIsApplied(): void
    {
        $dto = new class {
            #[Field(label: 'f.name', help: 'f.help', placeholder: 'f.ph', default: 'seed', required: true, readonly: true)]
            public string $name = '';
        };

        $definition = (new FieldSchemaReader())->read($dto::class)[0]->toDefinition();

        self::assertNotNull($definition->label);
        self::assertNotNull($definition->help);
        self::assertSame('seed', $definition->default);
        self::assertTrue($definition->constraints->required);
        self::assertTrue($definition->attributes['readonly']);
        self::assertSame(['key' => 'f.ph', 'component' => ''], $definition->attributes['placeholder']);
    }

    public function testTypeInferenceCoversFloatDateAndUntyped(): void
    {
        $dto = new class {
            #[Field]
            public float $ratio = 0.0;

            #[Field]
            public ?DateTimeImmutable $when = null;

            #[Field]
            public $loose = null; // no PHP type → falls back to TEXT
        };

        $types = [];
        foreach ((new FieldSchemaReader())->read($dto::class) as $field) {
            $definition = $field->toDefinition();
            $types[$definition->name] = $definition->type;
        }

        self::assertSame(FieldType::FLOAT, $types['ratio']);
        self::assertSame(FieldType::DATE, $types['when']);
        self::assertSame(FieldType::TEXT, $types['loose']);
    }

    /**
     * @return array<string, FieldDefinition>
     */
    private function definitionsByName(): array
    {
        $definitions = [];
        foreach ((new FieldSchemaReader())->read(ProfileDto::class) as $field) {
            $definition = $field->toDefinition();
            $definitions[$definition->name] = $definition;
        }

        return $definitions;
    }
}
