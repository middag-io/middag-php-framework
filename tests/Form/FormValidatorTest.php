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

use Middag\Framework\Form\ConditionEvaluator;
use Middag\Framework\Form\Field\IntField;
use Middag\Framework\Form\Field\TextField;
use Middag\Framework\Form\FormValidator;
use Middag\Ui\Shared\Enum\ConditionOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Required-ness is read from the typed FieldConstraints (ui 0.5.0); attribute
 * max/min/pattern and custom Symfony constraints are enforced server-side via
 * the Symfony Validator.
 *
 * @internal
 */
#[CoversClass(FormValidator::class)]
final class FormValidatorTest extends TestCase
{
    #[Test]
    public function staticRequiredFieldFromConstraintsErrorsWhenEmpty(): void
    {
        $errors = $this->validator()->validate([(new TextField('name'))->required()], []);

        self::assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function requiredFieldPassesWhenPresent(): void
    {
        $errors = $this->validator()->validate([(new TextField('name'))->required()], ['name' => 'Acme']);

        self::assertSame([], $errors);
    }

    #[Test]
    public function optionalFieldDoesNotError(): void
    {
        $errors = $this->validator()->validate([new TextField('name')], []);

        self::assertSame([], $errors);
    }

    #[Test]
    public function requiredWhenConditionMakesFieldRequired(): void
    {
        $schema = [(new TextField('reason'))->requiredWhen('type', ConditionOperator::EQ, 'other')];

        self::assertArrayHasKey('reason', $this->validator()->validate($schema, ['type' => 'other']));
        self::assertSame([], $this->validator()->validate($schema, ['type' => 'standard']));
    }

    #[Test]
    public function attributeMaxConstraintStillEnforced(): void
    {
        $errors = $this->validator()->validate([(new TextField('code'))->max(3)], ['code' => 'abcd']);

        self::assertArrayHasKey('code', $errors);
    }

    #[Test]
    public function attributeMinAndPatternConstraintsStillEnforced(): void
    {
        $min = $this->validator()->validate([(new TextField('pin'))->min(4)], ['pin' => 'ab']);
        self::assertArrayHasKey('pin', $min);

        $pattern = $this->validator()->validate(
            [(new TextField('slug'))->pattern('/^[a-z]+$/')],
            ['slug' => 'NOPE1'],
        );
        self::assertArrayHasKey('slug', $pattern);
    }

    #[Test]
    public function numericFieldMaxBoundsTheValueNotTheLength(): void
    {
        // A 6-digit string is short by char-length but over the numeric max.
        $errors = $this->validator()->validate([(new IntField('estimate'))->max(100000)], ['estimate' => '999999']);
        self::assertArrayHasKey('estimate', $errors);

        $ok = $this->validator()->validate([(new IntField('estimate'))->max(100000)], ['estimate' => '42']);
        self::assertSame([], $ok);
    }

    #[Test]
    public function numericFieldMinBoundsTheValue(): void
    {
        $errors = $this->validator()->validate([(new IntField('qty'))->min(10)], ['qty' => '3']);

        self::assertArrayHasKey('qty', $errors);
    }

    #[Test]
    public function customConstraintIsEnforced(): void
    {
        $errors = $this->validator()->validate([(new TextField('token'))->rule(new Assert\EqualTo('expected'))], ['token' => 'nope']);
        self::assertArrayHasKey('token', $errors);

        $ok = $this->validator()->validate([(new TextField('token'))->rule(new Assert\EqualTo('expected'))], ['token' => 'expected']);
        self::assertSame([], $ok);
    }

    #[Test]
    public function customCallbackConstraintIsEnforced(): void
    {
        $rule = new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
            if ($value !== 'ok') {
                $context->buildViolation('The flag field failed.')->addViolation();
            }
        });

        $errors = $this->validator()->validate([(new TextField('flag'))->rule($rule)], ['flag' => 'bad']);

        self::assertArrayHasKey('flag', $errors);
    }

    private function validator(): FormValidator
    {
        return new FormValidator(new ConditionEvaluator());
    }
}
