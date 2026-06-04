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

use Middag\Framework\Http\ValidationErrorSerializer;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\FallbackTranslator;
use Middag\Framework\Translation\TranslatableMessage;
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
}
