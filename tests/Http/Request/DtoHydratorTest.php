<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http\Request;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Framework\Http\Request\DtoHydrator;
use Middag\Framework\Tests\Http\Fixture\ValidatedTicketDto;
use Middag\Framework\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DtoHydrator::class)]
final class DtoHydratorTest extends TestCase
{
    #[Test]
    public function hydratesAndCoercesScalarTypes(): void
    {
        $dto = (new DtoHydrator())->hydrate(ValidatedTicketDto::class, [
            'subject' => 'Printer down',
            'priority' => 'high',
            'customer_id' => '42', // a numeric string coerces into the int property
        ]);

        self::assertInstanceOf(ValidatedTicketDto::class, $dto);
        self::assertSame('Printer down', $dto->subject);
        self::assertSame('high', $dto->priority);
        self::assertSame(42, $dto->customerId);
        self::assertNull($dto->agentId);
    }

    #[Test]
    public function mapsSnakeCaseInputToCamelCaseProperties(): void
    {
        $dto = (new DtoHydrator())->hydrate(ValidatedTicketDto::class, [
            'subject' => 'Slow VPN',
            'priority' => 'low',
            'customer_id' => 7,
            'agent_id' => 9,
        ]);

        self::assertSame(9, $dto->agentId);
    }

    #[Test]
    public function throwsWithFieldErrorsOnMissingRequiredAndBadChoice(): void
    {
        try {
            (new DtoHydrator())->hydrate(ValidatedTicketDto::class, [
                'priority' => 'bogus',
                'customer_id' => 5,
            ]);

            self::fail('expected MiddagValidationException');
        } catch (MiddagValidationException $middagValidationException) {
            $errors = $middagValidationException->errors();

            self::assertArrayHasKey('subject', $errors);  // NotBlank on the absent required field
            self::assertArrayHasKey('priority', $errors); // outside the allowed choices
            self::assertArrayNotHasKey('customer_id', $errors);
        }
    }

    #[Test]
    public function reportsTypeMismatchAsFieldError(): void
    {
        try {
            (new DtoHydrator())->hydrate(ValidatedTicketDto::class, [
                'subject' => 'Disk full',
                'priority' => 'normal',
                'customer_id' => 'not-a-number', // cannot coerce into int
            ]);

            self::fail('expected MiddagValidationException');
        } catch (MiddagValidationException $middagValidationException) {
            self::assertArrayHasKey('customer_id', $middagValidationException->errors());
        }
    }

    #[Test]
    public function constraintErrorsAreTranslatableMessages(): void
    {
        try {
            (new DtoHydrator())->hydrate(ValidatedTicketDto::class, [
                'priority' => 'bogus',
                'customer_id' => 5,
            ]);
            self::fail('expected MiddagValidationException');
        } catch (MiddagValidationException $exception) {
            $priority = $exception->errors()['priority'];
            self::assertInstanceOf(TranslatableMessage::class, $priority);
            self::assertSame('validators', $priority->domain);
            self::assertStringStartsWith('validation.', $priority->key);
        }
    }

    #[Test]
    public function denormalizationErrorRoutesThroughTheTranslatorNotAHardcodedString(): void
    {
        try {
            (new DtoHydrator())->hydrate(ValidatedTicketDto::class, [
                'subject' => 'Disk full',
                'priority' => 'normal',
                'customer_id' => 'not-a-number',
            ]);
            self::fail('expected MiddagValidationException');
        } catch (MiddagValidationException $exception) {
            $raw = $exception->errors()['customer_id'];
            // The field may carry a single TranslatableMessage or a list when additional
            // constraint violations fire on the same field (e.g. NotNull after type error).
            $first = is_array($raw) ? $raw[0] : $raw;
            self::assertInstanceOf(TranslatableMessage::class, $first);
            self::assertSame('validation.invalid_type', $first->key);  // was hardcoded 'This value is not valid.'
            self::assertSame('This value is not valid.', $first->defaultMessage);
        }
    }
}
