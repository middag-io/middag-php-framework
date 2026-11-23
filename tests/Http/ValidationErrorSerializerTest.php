<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Http;

use InvalidArgumentException;
use Middag\Framework\Http\ValidationErrorSerializer;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\FallbackTranslator;
use Middag\Framework\Translation\TranslatableMessage;
use Middag\Ui\Form\FieldMessage;
use Middag\Ui\Form\FormErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ValidationErrorSerializer::class)]
final class ValidationErrorSerializerTest extends TestCase
{
    #[Test]
    public function fallsBackToDefaultMessageWhenNoCatalogueResolvesTheKey(): void
    {
        $serializer = new ValidationErrorSerializer(new FallbackTranslator());

        $out = $serializer->serialize([
            'agent_id' => new TranslatableMessage('validation.type', 'validators', ['type' => 'numeric'], 'This value should be of type numeric.'),
        ]);

        self::assertSame([
            'agent_id' => [
                'message' => 'This value should be of type numeric.',
                'key' => 'validation.type',
                'domain' => 'validators',
                'params' => ['type' => 'numeric'],
            ],
        ], $out);
    }

    #[Test]
    public function resolvesMessageThroughABoundTranslator(): void
    {
        $translator = new class implements TranslatorInterface {
            public function get(string $key, string $component = '', array $params = []): string
            {
                return 'TRANSLATED:' . $component;
            }

            public function has(string $key, string $component = ''): bool
            {
                return true;
            }
        };

        $out = (new ValidationErrorSerializer($translator))->serialize([
            'agent_id' => new TranslatableMessage('validation.type', 'validators', ['type' => 'numeric'], 'fallback'),
        ]);

        self::assertSame('TRANSLATED:validators', $out['agent_id']['message']);
    }

    #[Test]
    public function passesRawStringsThroughAndSerialisesLists(): void
    {
        $serializer = new ValidationErrorSerializer(new FallbackTranslator());

        $out = $serializer->serialize([
            'note' => 'a literal string',
            'title' => [
                new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.'),
                new TranslatableMessage('validation.length.too_short', 'validators', ['limit' => 3], 'Too short.'),
            ],
        ]);

        self::assertSame('a literal string', $out['note']);
        self::assertCount(2, $out['title']);
        self::assertSame('validation.not_blank', $out['title'][0]['key']);
        self::assertSame('validation.length.too_short', $out['title'][1]['key']);
    }

    #[Test]
    public function omitsEmptyParams(): void
    {
        $out = (new ValidationErrorSerializer(new FallbackTranslator()))->serialize([
            'title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.'),
        ]);

        self::assertSame([
            'title' => [
                'message' => 'This value should not be blank.',
                'key' => 'validation.not_blank',
                'domain' => 'validators',
            ],
        ], $out);
    }

    #[Test]
    public function formErrorsKeepsTheStructuredMessages(): void
    {
        $errors = (new ValidationErrorSerializer(new FallbackTranslator()))->formErrors([
            'note' => 'a literal string',
            'title' => new TranslatableMessage('validation.not_blank', 'validators', [], 'This value should not be blank.'),
            'tags' => [
                'Too many tags',
                new TranslatableMessage('validation.unique', 'validators', ['limit' => 3], 'Tags must be unique.'),
            ],
        ]);

        self::assertInstanceOf(FormErrors::class, $errors);
        self::assertSame('a literal string', $errors->errors['note']);
        self::assertEquals(
            new FieldMessage('This value should not be blank.', 'validation.not_blank', 'validators'),
            $errors->errors['title'],
        );
        self::assertSame(
            '{"note":"a literal string",'
            . '"title":{"message":"This value should not be blank.","key":"validation.not_blank","domain":"validators"},'
            . '"tags":["Too many tags",{"message":"Tags must be unique.","key":"validation.unique","domain":"validators","params":{"limit":3}}]}',
            json_encode($errors, JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function formErrorsAndTheSerializedBodyAgreeOnEachMessage(): void
    {
        $map = ['title' => new TranslatableMessage('validation.length.too_short', 'validators', ['limit' => 3], 'Too short.')];
        $serializer = new ValidationErrorSerializer(new FallbackTranslator());

        self::assertSame(
            json_encode($serializer->serialize($map), JSON_THROW_ON_ERROR),
            json_encode($serializer->formErrors($map), JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function rejectsListParamsInTheBody(): void
    {
        // params are keyed by placeholder name; a list cannot encode as the
        // JSON object FieldMessage requires.
        $this->expectException(InvalidArgumentException::class);

        (new ValidationErrorSerializer(new FallbackTranslator()))->serialize([
            'title' => new TranslatableMessage('validation.custom', 'validators', ['x'], 'Bad.'),
        ]);
    }

    #[Test]
    public function rejectsListParamsInTheFlashedErrors(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ValidationErrorSerializer(new FallbackTranslator()))->formErrors([
            'title' => [new TranslatableMessage('validation.custom', 'validators', ['x'], 'Bad.')],
        ]);
    }
}
