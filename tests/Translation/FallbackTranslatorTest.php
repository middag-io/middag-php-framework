<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Translation;

use Locale;
use Middag\Framework\Translation\FallbackTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FallbackTranslator::class)]
final class FallbackTranslatorTest extends TestCase
{
    public function testReturnsKeyVerbatimWithoutParams(): void
    {
        $translator = new FallbackTranslator();

        self::assertSame('users.greeting', $translator->get('users.greeting'));
    }

    public function testInterpolatesNamedPlaceholders(): void
    {
        $translator = new FallbackTranslator();

        self::assertSame(
            'Hello Ada, you have 3 messages',
            $translator->get('Hello %name%, you have %count% messages', '', ['%name%' => 'Ada', '%count%' => 3]),
        );
    }

    public function testSelectsPluralFormByCount(): void
    {
        $previous = Locale::getDefault();
        Locale::setDefault('en');

        try {
            $translator = new FallbackTranslator();
            $message = 'one apple|%count% apples';

            self::assertSame('one apple', $translator->get($message, '', ['%count%' => 1]));
            self::assertSame('5 apples', $translator->get($message, '', ['%count%' => 5]));
            self::assertSame('0 apples', $translator->get($message, '', ['%count%' => 0]));
        } finally {
            Locale::setDefault($previous);
        }
    }

    public function testHasReturnsFalseWithNoCatalogue(): void
    {
        $translator = new FallbackTranslator();

        self::assertFalse($translator->has('any.key'));
    }
}
