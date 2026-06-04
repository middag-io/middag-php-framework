<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Filesystem\Contract;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Filesystem\LocalFilesystem;

/**
 * Infrastructure port for file storage, so domain/use-case code reads and
 * writes through a thin contract instead of touching the host's disk layout.
 *
 * Paths are logical and relative to a storage root the implementation owns;
 * implementations MUST keep callers confined to that root (no `..` traversal).
 * A host adapter maps the same calls onto the platform's protected storage
 * (Moodle moodledata, WordPress wp-content/uploads), and a Flysystem-backed
 * impl can be bound for cloud/abstract targets (see the `league/flysystem`
 * suggest) — resolving the framework's "no secure upload location" gap.
 *
 * Default OSS impl: {@see LocalFilesystem} (a root-confined local directory).
 *
 * @api
 */
interface FilesystemInterface
{
    /**
     * True when a readable file exists at the logical path.
     */
    public function exists(string $path): bool;

    /**
     * Read the whole file.
     *
     * @throws MiddagInfrastructureException when the file is missing or unreadable
     */
    public function read(string $path): string;

    /**
     * Write the contents, creating intermediate directories as needed and
     * overwriting any existing file.
     *
     * @throws MiddagInfrastructureException when the file cannot be written
     */
    public function write(string $path, string $contents): void;

    /**
     * Remove the file. A missing file is not an error (idempotent delete).
     *
     * @throws MiddagInfrastructureException when an existing file cannot be removed
     */
    public function delete(string $path): void;
}
