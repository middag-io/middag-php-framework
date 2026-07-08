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

use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\ConditionEvaluator;
use Middag\Framework\Form\FieldFactory;
use Middag\Framework\Form\FormValidator;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Drives the AbstractForm lifecycle (hydrate → validate → submit) through a
 * concrete anonymous subclass declaring a single required field.
 *
 * @internal
 */
#[CoversClass(AbstractForm::class)]
final class AbstractFormTest extends TestCase
{
    #[Test]
    public function freshFormIsNeitherSubmittedNorValid(): void
    {
        $form = $this->form();

        self::assertInstanceOf(FormState::class, $form->state());
        self::assertFalse($form->state()->isSubmitted());
        self::assertFalse($form->isSubmittedAndValid());
        self::assertSame([], $form->errors());
    }

    #[Test]
    public function schemaExposesTheDeclaredFields(): void
    {
        $schema = $this->form()->schema();

        self::assertCount(1, $schema);
        self::assertInstanceOf(FieldInterface::class, $schema[0]);
        self::assertSame('title', $schema[0]->name());
    }

    #[Test]
    public function hydrateMarksTheFormSubmittedAndExposesValues(): void
    {
        $form = $this->form();
        $form->hydrate(['title' => 'Hello']);

        self::assertTrue($form->state()->isSubmitted());
        self::assertSame(['title' => 'Hello'], $form->validated());
    }

    #[Test]
    public function validateCollectsErrorsForAnInvalidSubmission(): void
    {
        $form = $this->form();
        $form->hydrate(['title' => '']);
        $form->validate();

        self::assertArrayHasKey('title', $form->errors());
        self::assertFalse($form->isSubmittedAndValid());
    }

    #[Test]
    public function validSubmissionPassesAndSurfacesValidatedValues(): void
    {
        $form = $this->form();
        $form->hydrate(['title' => 'A valid title']);
        $form->validate();

        self::assertSame([], $form->errors());
        self::assertTrue($form->isSubmittedAndValid());
        self::assertSame(['title' => 'A valid title'], $form->validated());
    }

    private function form(): FormInterface
    {
        $validator = new FormValidator(new ConditionEvaluator());

        return new class($validator) extends AbstractForm {
            public function schema(): array
            {
                return [FieldFactory::text('title')->required()];
            }
        };
    }
}
