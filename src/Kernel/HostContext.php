<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel;

use Middag\Framework\Kernel\Contract\HostComponentContextInterface;

/**
 * Composition-root registry for the active {@see HostComponentContextInterface}.
 *
 * The host composition root calls {@see self::set()} once during bootstrap;
 * adapter helpers (which are often static and live outside the DI graph) read
 * the registered context via {@see self::get()}. {@see self::get()} returns null
 * when no host has configured a context, letting callers fall back safely
 * instead of failing.
 *
 * Mirrors the {@see ContainerFactory} boot-seam philosophy: the generic adapter
 * exposes the seam, the host wires the concrete value.
 *
 * Multi-host limitation (read before using in a multi-component host): this is a
 * single global slot, so it models exactly one active host per process. It is
 * safe for single-component hosts — Moodle boots one plugin per request. It is
 * NOT safe when several host components share a process: multiple WordPress
 * plugins built on middag-io/wordpress in the same request would each call
 * {@see self::set()} and the last write silently wins, leaking one plugin's
 * identity, version, and paths into the others. Such consumers must NOT read
 * this seam; they inject {@see HostComponentContextInterface} through their own
 * DI container (each plugin has its own container, so isolation is natural). A
 * keyed-by-component registry may replace this single slot in a future minor if
 * a framework-level multi-component need arises; until then the slot stays as
 * the single-host seam.
 *
 * @api
 */
final class HostContext
{
    private static ?HostComponentContextInterface $context = null;

    private function __construct() {}

    /**
     * Register the host context. Called once by the host composition root during
     * bootstrap.
     */
    public static function set(HostComponentContextInterface $context): void
    {
        self::$context = $context;
    }

    /**
     * Resolve the registered host context, or null when no host has configured one.
     */
    public static function get(): ?HostComponentContextInterface
    {
        return self::$context;
    }

    /**
     * Clear the registered context (test isolation / re-boot).
     */
    public static function reset(): void
    {
        self::$context = null;
    }
}
