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
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Framework\Translation\FallbackTranslator;
use Middag\Framework\Translation\SymfonyTranslatorAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SymfonyTranslatorAdapter::class)]
final class SymfonyTranslatorAdapterTest extends TestCase
{
    public function testForwardsMessageDomainAndParametersToTheFrameworkTranslator(): void
    {
        $spy = new class implements TranslatorInterface {
            public string $key = '';

            public string $component = '';

            /** @var array<string, mixed> */
            public array $params = [];

            public function get(string $key, string $component = '', array $params = []): string
            {
                $this->key = $key;
                $this->component = $component;
                $this->params = $params;

                return 'translated';
            }

            public function has(string $key, string $component = ''): bool
            {
                return true;
            }
        };

        $adapter = new SymfonyTranslatorAdapter($spy);
        $result = $adapter->trans('message.id', ['%count%' => 2], 'validators');

        self::assertSame('translated', $result);
        self::assertSame('message.id', $spy->key);
        self::assertSame('validators', $spy->component, 'the Symfony domain maps to the framework component');
        self::assertSame(['%count%' => 2], $spy->params);
    }

    public function testPluralisesThroughTheWrappedFallbackTranslator(): void
    {
        $previous = Locale::getDefault();
        Locale::setDefault('en');

        try {
            $adapter = new SymfonyTranslatorAdapter(new FallbackTranslator());

            self::assertSame(
                '3 apples',
                $adapter->trans('one apple|%count% apples', ['%count%' => 3], 'validators'),
            );
        } finally {
            Locale::setDefault($previous);
        }
    }

    public function testGetLocaleReturnsANonEmptyString(): void
    {
        $adapter = new SymfonyTranslatorAdapter(new FallbackTranslator());

        self::assertNotSame('', $adapter->getLocale());
    }
}
