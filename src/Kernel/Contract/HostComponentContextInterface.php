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

use Middag\Framework\Kernel\HostContext;

/**
 * Neutral runtime context a host integration exposes to the framework adapters.
 *
 * Each host (Moodle, WordPress, standalone) describes itself through this
 * contract so adapter code never hard-codes a specific consumer's identity,
 * version, or paths. The host composition root builds an implementation once at
 * boot and registers it (see {@see HostContext}); adapter
 * helpers then read neutral values instead of consumer-specific globals.
 *
 * The contract intentionally stays host-agnostic: it models *what* every host
 * must provide, not *how* a particular platform resolves it. Hosts that have no
 * meaningful value for an optional accessor return a safe default (or null) so
 * callers can degrade gracefully.
 *
 * @api
 */
interface HostComponentContextInterface
{
    /**
     * Stable identifier of the host component/package that owns the boot cycle
     * (e.g. a Moodle frankenstyle component or a WordPress plugin slug).
     */
    public function componentName(): string;

    /**
     * Version identity used for asset cache-busting and similar host concerns.
     */
    public function assetVersion(): string;

    /**
     * Absolute base path the host exposes for resolving bundled resources
     * (templates, assets, ...), or null when the host provides none. Callers
     * must degrade gracefully when this returns null.
     */
    public function basePath(): ?string;
}
