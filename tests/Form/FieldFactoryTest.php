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

use Closure;
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
use Middag\Framework\Form\FieldFactory;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Shared\Enum\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The static field factory: every builder returns the expected concrete field,
 * carries the given name, and declares the correct FieldType.
 *
 * The factory calls are wrapped in closures invoked inside the test body so the
 * coverage driver attributes them to FieldFactory (data providers run before
 * coverage collection begins).
 *
 * @internal
 */
#[CoversClass(FieldFactory::class)]
final class FieldFactoryTest extends TestCase
{
    /**
     * @param Closure(): FieldInterface    $build
     * @param class-string<FieldInterface> $expectedClass
     */
    #[Test]
    #[DataProvider('factoryCases')]
    public function factoryBuildsTheExpectedFieldTypeAndClass(
        Closure $build,
        string $expectedClass,
        FieldType $expectedType,
    ): void {
        $field = $build();

        self::assertInstanceOf($expectedClass, $field);

        $definition = $field->toDefinition();

        self::assertSame('example', $definition->name);
        self::assertSame($expectedType, $definition->type);
    }

    /**
     * @return iterable<string, array{0: Closure(): FieldInterface, 1: class-string<FieldInterface>, 2: FieldType}>
     */
    public static function factoryCases(): iterable
    {
        yield 'text' => [static fn (): FieldInterface => FieldFactory::text('example'), TextField::class, FieldType::TEXT];

        yield 'textarea' => [static fn (): FieldInterface => FieldFactory::textarea('example'), TextareaField::class, FieldType::TEXTAREA];

        yield 'email' => [static fn (): FieldInterface => FieldFactory::email('example'), TextField::class, FieldType::EMAIL];

        yield 'password' => [static fn (): FieldInterface => FieldFactory::password('example'), TextField::class, FieldType::PASSWORD];

        yield 'url' => [static fn (): FieldInterface => FieldFactory::url('example'), TextField::class, FieldType::URL];

        yield 'integer' => [static fn (): FieldInterface => FieldFactory::integer('example'), IntField::class, FieldType::INT];

        yield 'decimal' => [static fn (): FieldInterface => FieldFactory::decimal('example'), FloatField::class, FieldType::FLOAT];

        yield 'checkbox' => [static fn (): FieldInterface => FieldFactory::checkbox('example'), GenericField::class, FieldType::CHECKBOX];

        yield 'toggle' => [static fn (): FieldInterface => FieldFactory::toggle('example'), GenericField::class, FieldType::SWITCH];

        yield 'date' => [static fn (): FieldInterface => FieldFactory::date('example'), DateField::class, FieldType::DATE];

        yield 'datetime' => [static fn (): FieldInterface => FieldFactory::datetime('example'), DateField::class, FieldType::DATETIME];

        yield 'duration' => [static fn (): FieldInterface => FieldFactory::duration('example'), DurationField::class, FieldType::DURATION];

        yield 'entityPicker' => [static fn (): FieldInterface => FieldFactory::entityPicker('example'), EntityPickerField::class, FieldType::ENTITY_PICKER];

        yield 'file' => [static fn (): FieldInterface => FieldFactory::file('example'), FileField::class, FieldType::FILE];

        yield 'header' => [static fn (): FieldInterface => FieldFactory::header('example'), StaticField::class, FieldType::HEADER];

        yield 'hidden' => [static fn (): FieldInterface => FieldFactory::hidden('example'), GenericField::class, FieldType::HIDDEN];

        yield 'multiselect' => [static fn (): FieldInterface => FieldFactory::multiselect('example'), SelectField::class, FieldType::MULTISELECT];

        yield 'radio' => [static fn (): FieldInterface => FieldFactory::radio('example'), RadioField::class, FieldType::RADIO];

        yield 'select' => [static fn (): FieldInterface => FieldFactory::select('example'), SelectField::class, FieldType::SELECT];

        yield 'display' => [static fn (): FieldInterface => FieldFactory::display('example'), StaticField::class, FieldType::STATIC];
    }

    #[Test]
    public function constructorIsPrivateSoTheFactoryStaysStatic(): void
    {
        $constructor = (new ReflectionClass(FieldFactory::class))->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }
}
