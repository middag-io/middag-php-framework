<?php

declare(strict_types=1);

namespace Middag\Framework\Tests\Http\Request;

use Middag\Framework\Http\Request\ValidationErrorBag;
use Middag\Framework\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(ValidationErrorBag::class)]
final class ValidationErrorBagTest extends TestCase
{
    #[Test]
    public function buildsTranslatableMessagesKeyedBySnakeCaseField(): void
    {
        $violations = Validation::createValidator()->validate(
            ['agent_id' => 'x'],
            new Assert\Collection(['agent_id' => new Assert\Type('int')]),
        );

        $errors = (new ValidationErrorBag())->fromViolations($violations);

        self::assertArrayHasKey('agent_id', $errors);
        $message = $errors['agent_id'];
        self::assertInstanceOf(TranslatableMessage::class, $message);
        self::assertSame('validation.type', $message->key);
        self::assertSame('validators', $message->domain);
        self::assertArrayHasKey('type', $message->params);          // braces stripped from '{{ type }}'
        self::assertNotNull($message->defaultMessage);              // interpolated English fallback
    }

    #[Test]
    public function promotesAFieldToAListOnTheSecondError(): void
    {
        $bag = new ValidationErrorBag();
        $errors = [];

        $bag->add($errors, 'title', new TranslatableMessage('validation.not_blank', 'validators'));
        $bag->add($errors, 'title', new TranslatableMessage('validation.length.too_short', 'validators'));

        self::assertIsArray($errors['title']);
        self::assertCount(2, $errors['title']);
    }

    #[Test]
    public function fieldNameStripsBracketsAndCamelCases(): void
    {
        $bag = new ValidationErrorBag();

        self::assertSame('agent_id', $bag->fieldName('[agentId]'));
        self::assertSame('subject', $bag->fieldName('subject'));
        self::assertSame('_', $bag->fieldName(''));
    }

    #[Test]
    public function denormalizationMessageCarriesItsKeyAndEnglishFallback(): void
    {
        $message = (new ValidationErrorBag())->denormalizationMessage();

        self::assertSame('validation.invalid_type', $message->key);
        self::assertSame('This value is not valid.', $message->defaultMessage);
    }
}
