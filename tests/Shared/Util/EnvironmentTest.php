<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Shared\Util;

use Middag\Framework\Shared\Util\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    private false|string $originalMiddagEnv = false;

    private false|string $originalAppEnv = false;

    protected function setUp(): void
    {
        $this->originalMiddagEnv = getenv('MIDDAG_ENV');
        $this->originalAppEnv = getenv('APP_ENV');

        putenv('MIDDAG_ENV');
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        $this->restore('MIDDAG_ENV', $this->originalMiddagEnv);
        $this->restore('APP_ENV', $this->originalAppEnv);
    }

    #[Test]
    public function defaultsToProductionWithoutAnySignal(): void
    {
        self::assertSame(Environment::ENV_PRODUCTION, Environment::getEnvironment());
        self::assertTrue(Environment::isProduction());
        self::assertFalse(Environment::isDevelopment());
        self::assertFalse(Environment::isTesting());
    }

    #[Test]
    #[DataProvider('middagEnvProvider')]
    public function middagEnvVariableIsNormalised(string $value, string $expected): void
    {
        putenv('MIDDAG_ENV=' . $value);

        self::assertSame($expected, Environment::getEnvironment());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function middagEnvProvider(): iterable
    {
        yield 'dev maps to development' => ['dev', Environment::ENV_DEVELOPMENT];

        yield 'local maps to development' => ['local', Environment::ENV_DEVELOPMENT];

        yield 'debug maps to development' => ['debug', Environment::ENV_DEVELOPMENT];

        yield 'development maps to development' => ['development', Environment::ENV_DEVELOPMENT];

        yield 'test maps to testing' => ['test', Environment::ENV_TESTING];

        yield 'testing maps to testing' => ['testing', Environment::ENV_TESTING];

        yield 'ci maps to testing' => ['ci', Environment::ENV_TESTING];

        yield 'production maps to production' => ['production', Environment::ENV_PRODUCTION];

        yield 'unknown maps to production' => ['staging', Environment::ENV_PRODUCTION];

        yield 'padded and uppercased normalises' => ['  DEV  ', Environment::ENV_DEVELOPMENT];
    }

    #[Test]
    public function appEnvIsUsedWhenMiddagEnvIsAbsent(): void
    {
        putenv('APP_ENV=development');

        self::assertSame(Environment::ENV_DEVELOPMENT, Environment::getEnvironment());
        self::assertTrue(Environment::isDevelopment());
    }

    #[Test]
    public function hostEnvironmentHookIsConsultedWhenNoEnvVarsPresent(): void
    {
        $probe = new class extends Environment {
            protected static function detectHostEnvironment(): string
            {
                return 'debug';
            }
        };

        self::assertSame(Environment::ENV_DEVELOPMENT, $probe::getEnvironment());
        self::assertTrue($probe::isDevelopment());
    }

    #[Test]
    public function emptyHostEnvironmentHintFallsThroughToProduction(): void
    {
        $probe = new class extends Environment {
            protected static function detectHostEnvironment(): string
            {
                return '';
            }
        };

        self::assertSame(Environment::ENV_PRODUCTION, $probe::getEnvironment());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function phpUnitConstantForcesTestingEnvironment(): void
    {
        define('PHPUNIT_TEST', true);

        self::assertSame(Environment::ENV_TESTING, Environment::getEnvironment());
        self::assertTrue(Environment::isTesting());
        self::assertFalse(Environment::isProduction());
        self::assertFalse(Environment::isDevelopment());
    }

    private function restore(string $var, false|string $value): void
    {
        if ($value === false) {
            putenv($var);

            return;
        }

        putenv($var . '=' . $value);
    }
}
