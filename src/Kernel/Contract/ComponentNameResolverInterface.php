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

/**
 * Resolves the native component identifier that owns the running boot cycle.
 *
 * For Moodle adapters this maps to the host plugin frankenstyle (e.g.
 * `local_example`). For WordPress adapters it maps to the plugin slug.
 * Consumers like {@see BootRethrowFailurePolicy} use it to classify classes under
 * `{component`\modules\{slug}\...} as native vs third-party.
 *
 * @api
 */
interface ComponentNameResolverInterface
{
    /**
     * Returns the native host component identifier (frankenstyle / plugin slug).
     */
    public function nativeComponent(): string;
}
