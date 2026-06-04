<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Kernel\Bootstrap;

use Middag\Framework\Kernel\Bootstrap\EnvConfigResolver;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class EnvConfigResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['STRIPE_SECRETKEY'], $_ENV['STRIPE_SECRETKEY_BR']);
    }

    public function testReadsEnvByUppercaseKey(): void
    {
        $_ENV['STRIPE_SECRETKEY'] = 'sk_test_123';
        $resolver = new EnvConfigResolver();

        self::assertSame('sk_test_123', $resolver->get('stripe_secretkey'));
    }

    public function testEntityScopedKeyTakesPrecedenceOverGlobal(): void
    {
        $_ENV['STRIPE_SECRETKEY'] = 'global';
        $_ENV['STRIPE_SECRETKEY_BR'] = 'br_specific';
        $resolver = new EnvConfigResolver();

        self::assertSame('br_specific', $resolver->get('stripe_secretkey', 'br'));
    }

    public function testFallsBackToOverridesWhenEnvAbsent(): void
    {
        $resolver = new EnvConfigResolver(['STRIPE_SECRETKEY' => 'from_overrides']);

        self::assertSame('from_overrides', $resolver->get('stripe_secretkey'));
    }

    public function testReturnsDefaultWhenAllSourcesEmpty(): void
    {
        $resolver = new EnvConfigResolver();

        self::assertSame('fallback', $resolver->get('missing_key', null, 'fallback'));
    }

    public function testHasReturnsFalseWhenValueAbsentOrEmpty(): void
    {
        $resolver = new EnvConfigResolver();
        self::assertFalse($resolver->has('missing_key'));

        $_ENV['STRIPE_SECRETKEY'] = '';
        self::assertFalse($resolver->has('stripe_secretkey'));

        $_ENV['STRIPE_SECRETKEY'] = 'value';
        self::assertTrue($resolver->has('stripe_secretkey'));
    }
}
