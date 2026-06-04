<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Bootstrap;

use Middag\Framework\Kernel\Contract\ConfigResolverInterface;

/**
 * Default ConfigResolver for standalone deployments.
 *
 * Resolution order: $_ENV[KEY_ENTITY] → $_ENV[KEY] → in-memory overrides → default.
 *
 * Keys are uppercased and entity slugs appended as `_SLUG` suffix.
 * Suitable for 12-factor apps using dotenv or container env vars.
 *
 * @api
 */
final class EnvConfigResolver implements ConfigResolverInterface
{
    /**
     * @param array<string, string> $overrides In-memory key=value fallbacks
     */
    public function __construct(private array $overrides = []) {}

    public function get(string $key, ?string $entitySlug = null, string $default = ''): string
    {
        $upper = strtoupper($key);
        $scoped = $entitySlug !== null ? $upper . '_' . strtoupper($entitySlug) : null;

        if ($scoped !== null && isset($_ENV[$scoped]) && $_ENV[$scoped] !== '') {
            return (string) $_ENV[$scoped];
        }

        if (isset($_ENV[$upper]) && $_ENV[$upper] !== '') {
            return (string) $_ENV[$upper];
        }

        if ($scoped !== null && isset($this->overrides[$scoped])) {
            return $this->overrides[$scoped];
        }

        return $this->overrides[$upper] ?? $default;
    }

    public function has(string $key, ?string $entitySlug = null): bool
    {
        return $this->get($key, $entitySlug) !== '';
    }
}
