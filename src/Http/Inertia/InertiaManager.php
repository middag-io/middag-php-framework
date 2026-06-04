<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Http\Inertia;

/**
 * Manages globally shared Inertia props.
 *
 * Static by design (configuration seam, invariant protocol) — see the rationale
 * on {@see InertiaAdapter}.
 *
 * Host-facing: sharing global props is the documented adapter boot seam, so this
 * is part of the supported integration surface.
 *
 * @api
 */
class InertiaManager
{
    /** @var array<string,mixed> */
    protected static array $shared = [];

    /**
     * Share data globally across all Inertia responses.
     */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Returns the shared prop map RAW — closures are NOT executed here.
     *
     * Resolution is the sole responsibility of
     * {@see InertiaResponse::resolveProps()}, so a shared closure is invoked
     * exactly once (no double-pass) and a partial reload can skip the closures
     * of keys it did not request.
     *
     * @return array<string, mixed>
     */
    public static function getShared(): array
    {
        return self::$shared;
    }

    /**
     * Clear all shared props.
     *
     * Reset seam for long-running workers (state must not bleed between
     * requests) and for test isolation.
     */
    public static function flush(): void
    {
        self::$shared = [];
    }
}
