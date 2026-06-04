<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Shared\Util;

/**
 * Environment detection (platform-agnostic).
 *
 * Static helper exposing `isDevelopment()` / `isProduction()` / `isTesting()`.
 * Adapters subclass this and override `detectHostEnvironment()` to plug in
 * platform-specific signals (e.g. a host config flag or platform env var).
 *
 * @api
 */
class Environment
{
    /** @var string Production environment identifier */
    public const ENV_PRODUCTION = 'production';

    /** @var string Development environment identifier */
    public const ENV_DEVELOPMENT = 'development';

    /** @var string Testing environment identifier */
    public const ENV_TESTING = 'testing';

    public static function isDevelopment(): bool
    {
        return static::getEnvironment() === self::ENV_DEVELOPMENT;
    }

    public static function isProduction(): bool
    {
        return static::getEnvironment() === self::ENV_PRODUCTION;
    }

    public static function isTesting(): bool
    {
        return static::getEnvironment() === self::ENV_TESTING;
    }

    /**
     * Returns the current environment name.
     *
     * Resolution order:
     *   1. `PHPUNIT_TEST` constant defined → testing.
     *   2. `MIDDAG_ENV` / `APP_ENV` environment variable → normalized.
     *   3. `static::detectHostEnvironment()` adapter hook → normalized.
     *   4. default: production.
     */
    public static function getEnvironment(): string
    {
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            return self::ENV_TESTING;
        }

        foreach (['MIDDAG_ENV', 'APP_ENV'] as $var) {
            $value = getenv($var);
            if ($value !== false && $value !== '') {
                return self::normalizeEnv($value);
            }
        }

        $host = static::detectHostEnvironment();
        if ($host !== null && $host !== '') {
            return self::normalizeEnv($host);
        }

        return self::ENV_PRODUCTION;
    }

    /**
     * Adapter hook — return the host platform's environment hint or null.
     *
     * Default: no host-specific detection. A host-specific subclass
     * inspects platform globals/config and returns a normalized string.
     */
    protected static function detectHostEnvironment(): ?string
    {
        return null;
    }

    protected static function normalizeEnv(string $env): string
    {
        $env = strtolower(trim($env));

        if (in_array($env, ['dev', 'local', 'debug', 'development'], true)) {
            return self::ENV_DEVELOPMENT;
        }

        if (in_array($env, ['test', 'testing', 'ci'], true)) {
            return self::ENV_TESTING;
        }

        return self::ENV_PRODUCTION;
    }
}
