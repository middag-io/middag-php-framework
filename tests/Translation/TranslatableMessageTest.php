<?php

declare(strict_types=1);

namespace Middag\Framework\Tests\Translation;

use Middag\Framework\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(TranslatableMessage::class)]
final class TranslatableMessageTest extends TestCase
{
    #[Test]
    public function serializesKeyAndDomainAlwaysOmittingEmptyParamsAndNullDefault(): void
    {
        $message = new TranslatableMessage('validation.not_blank', 'validators');

        self::assertSame(['key' => 'validation.not_blank', 'domain' => 'validators'], $message->jsonSerialize());
    }

    #[Test]
    public function serializesParamsAndDefaultMessageWhenPresent(): void
    {
        $message = TranslatableMessage::of('validation.type', 'validators', ['type' => 'numeric'], 'This value should be of type numeric.');

        self::assertSame([
            'key' => 'validation.type',
            'domain' => 'validators',
            'params' => ['type' => 'numeric'],
            'defaultMessage' => 'This value should be of type numeric.',
        ], $message->jsonSerialize());
    }

    #[Test]
    public function transForwardsToTheSymfonyTranslator(): void
    {
        $translator = new class implements TranslatorInterface {
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return sprintf('%s|%s|%s', (string) $id, $domain ?? '', $parameters['type'] ?? '');
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        $message = TranslatableMessage::of('validation.type', 'validators', ['type' => 'numeric']);

        self::assertSame('validation.type|validators|numeric', $message->trans($translator));
    }
}
