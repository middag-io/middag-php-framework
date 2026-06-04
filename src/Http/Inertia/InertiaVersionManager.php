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
 * Manages the Inertia asset version hash.
 *
 * Platform-agnostic: supports manual overrides and an optional
 * adapter-supplied bundle path for hash-based cache busting. The framework
 * never assumes a particular asset-build layout — the Moodle/WP adapter wires
 * the bundle path during boot.
 *
 * Static by design (configuration seam, invariant protocol) — see the rationale
 * on {@see InertiaAdapter}.
 *
 * Host-facing: setting the asset version / bundle path is the documented
 * adapter boot seam, so this is part of the supported integration surface.
 *
 * @api
 */
class InertiaVersionManager
{
    protected static ?string $manualVersion = null;

    protected static ?string $bundlePath = null;

    /**
     * Set the application version manually (useful for cache busting).
     */
    public static function setVersion(string $version): void
    {
        self::$manualVersion = $version;
    }

    /**
     * Configure the absolute path to the compiled frontend bundle.
     *
     * Adapter (Moodle/WP) calls this during boot. When set and the file
     * exists, {@see self::getVersion()} returns an md5 hash of its contents.
     */
    public static function setBundlePath(string $path): void
    {
        self::$bundlePath = $path;
    }

    /**
     * Get the current application version for Inertia payloads.
     *
     * @return string Version string (manual override, bundle hash, or 'dev')
     */
    public static function getVersion(): string
    {
        if (self::$manualVersion !== null) {
            return self::$manualVersion;
        }

        if (self::$bundlePath !== null && file_exists(self::$bundlePath)) {
            $hash = md5_file(self::$bundlePath);

            if ($hash !== false) {
                return $hash;
            }
        }

        return 'dev';
    }
}
