<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Kernel\Loader;

use Middag\Framework\Kernel\Contract\HookfileLoaderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Abstract base for hookfile loaders.
 *
 * Caches discovered paths and isolates load failures so that one broken
 * hookfile does not abort the boot cycle. Platform adapters extend this and
 * implement `discover_paths()` with their own discovery rules (module
 * directories, plugin directories, etc.).
 *
 * @internal
 */
abstract class HookfileLoader implements HookfileLoaderInterface
{
    /** @var null|string[] cached discovery result */
    private ?array $cachedPaths = null;

    /** @var array<string, true> paths that have already been loaded */
    private array $loaded = [];

    /** @var array<string, true> paths that have been suspended (failed) */
    private array $suspended = [];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    /**
     * Return the discovered paths, computing them on first call.
     *
     * @return string[]
     */
    final public function discover(): array
    {
        $this->cachedPaths ??= $this->discoverPaths();

        return $this->cachedPaths;
    }

    /**
     * Load one hookfile. Already-loaded paths are no-ops; suspended paths
     * stay suspended (no retry). Failures are caught and logged.
     */
    final public function load(string $path): bool
    {
        if (isset($this->loaded[$path])) {
            return true;
        }

        if (isset($this->suspended[$path])) {
            return false;
        }

        try {
            $this->includeHookfile($path);
            $this->loaded[$path] = true;

            return true;
        } catch (Throwable $throwable) {
            $this->suspended[$path] = true;
            $this->logger->error('Hookfile load failed; suspending: ' . $path, [
                'exception' => $throwable,
            ]);

            return false;
        }
    }

    /**
     * Concrete adapters define how candidate paths are discovered.
     *
     * @return string[]
     */
    abstract protected function discoverPaths(): array;

    /**
     * Include the hookfile. Override only if isolation semantics need to
     * change (e.g., scope-clean require inside a closure).
     */
    protected function includeHookfile(string $path): void
    {
        require_once $path;
    }
}
