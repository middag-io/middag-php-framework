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

use Throwable;

/**
 * Registry of modules that failed during boot.
 *
 * A boot-failure policy ({@see BootFailurePolicyInterface}) marks, inspects and
 * short-circuits modules whose bootstrap raised, so their remaining artifacts are
 * skipped for the rest of the boot cycle. Consumers provide a concrete
 * implementation backed by their own request-scoped storage.
 *
 * The generic pipe carries only a plain `$distribution` classification string; the
 * meaning of that string (and any tier-based isolation policy) is a consumer
 * concern — the framework holds no opinion on its values.
 *
 * @api
 */
interface FailedModuleRegistryInterface
{
    /**
     * Mark a module as failed for the current boot cycle.
     */
    public function register(string $slug, Throwable $exception, string $distribution): void;

    /**
     * Returns whether the given module slug has been previously registered as failed.
     */
    public function has(string $slug): bool;

    /**
     * Returns the full failure registry keyed by module slug.
     *
     * @return array<string, array{exception: Throwable, distribution: string, message: string, timestamp: int}>
     */
    public function all(): array;
}
