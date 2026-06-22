<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Filesystem;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;

/**
 * Default OSS filesystem: a local directory confined to a configured root.
 *
 * Every logical path is resolved lexically against the root and rejected if it
 * escapes (no `..` traversal, no absolute-path breakout, no null bytes), so a
 * caller can never read or write outside the storage area. Standalone-friendly;
 * host adapters bind their own impl pointed at protected platform storage
 * (moodledata, wp-content) or a Flysystem-backed target.
 *
 * @api
 */
final readonly class LocalFilesystem implements FilesystemInterface
{
    private string $root;

    /**
     * @param string $root absolute directory all operations are confined to
     */
    public function __construct(string $root)
    {
        // Collapse redundant separators before storing: resolve() normalises
        // every path lexically, so a root carrying "//" (e.g. a base dir that
        // already ends in "/") would otherwise never match the prefix check
        // and reject every operation as escaping the root.
        $this->root = rtrim((string) preg_replace('#/+#', '/', $root), '/');
    }

    public function exists(string $path): bool
    {
        return is_file($this->resolve($path));
    }

    public function read(string $path): string
    {
        $resolved = $this->resolve($path);

        if (!is_file($resolved)) {
            throw new MiddagInfrastructureException(sprintf('File "%s" not found.', $path));
        }

        $contents = @file_get_contents($resolved);
        if ($contents === false) {
            throw new MiddagInfrastructureException(sprintf('File "%s" could not be read.', $path));
        }

        return $contents;
    }

    public function write(string $path, string $contents): void
    {
        $resolved = $this->resolve($path);

        $directory = \dirname($resolved);
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new MiddagInfrastructureException(sprintf('Directory for "%s" could not be created.', $path));
        }

        if (@file_put_contents($resolved, $contents) === false) {
            throw new MiddagInfrastructureException(sprintf('File "%s" could not be written.', $path));
        }
    }

    public function delete(string $path): void
    {
        $resolved = $this->resolve($path);

        if (!is_file($resolved)) {
            // Idempotent: deleting an absent file is a no-op.
            return;
        }

        if (!@unlink($resolved)) {
            throw new MiddagInfrastructureException(sprintf('File "%s" could not be deleted.', $path));
        }
    }

    /**
     * Resolve a logical path to an absolute one confined to the root.
     *
     * Normalizes `.`/`..` segments lexically (so the target need not exist yet)
     * and rejects anything that would land outside the root.
     *
     * @throws MiddagInfrastructureException when the path escapes the root or holds a null byte
     */
    private function resolve(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new MiddagInfrastructureException('Path contains a null byte.');
        }

        $segments = [];
        foreach (explode('/', $this->root . '/' . ltrim($path, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        $resolved = '/' . implode('/', $segments);

        if ($resolved !== $this->root && !str_starts_with($resolved, $this->root . '/')) {
            throw new MiddagInfrastructureException(sprintf('Path "%s" escapes the filesystem root.', $path));
        }

        return $resolved;
    }
}
