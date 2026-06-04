<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Contract;

use Middag\Framework\Kernel\Bootstrap\EnvConfigResolver;

/**
 * Resolve configuration values with hierarchical fallback.
 *
 * Resolution order: environment variable → host config store → default.
 *
 * Keys are provider-scoped (e.g., 'paymentprovider_secretkey') and optionally
 * entity-scoped via the $entitySlug parameter (e.g., entity 'br' resolves the
 * uppercased env var and the host store key, both suffixed with the slug).
 *
 * @api
 *
 * @see EnvConfigResolver Default OSS implementation
 */
interface ConfigResolverInterface
{
    /**
     * Resolve a config value by key with optional entity scope.
     *
     * @param string      $key        Config key (e.g., 'paymentprovider_secretkey')
     * @param null|string $entitySlug Entity scope (e.g., 'br'). null = global.
     * @param string      $default    default value if not found anywhere
     */
    public function get(string $key, ?string $entitySlug = null, string $default = ''): string;

    /**
     * Check if a config key has a non-empty value.
     */
    public function has(string $key, ?string $entitySlug = null): bool;
}
