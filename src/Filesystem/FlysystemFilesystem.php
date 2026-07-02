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

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;

/**
 * Flysystem-backed {@see FilesystemInterface} (requires `league/flysystem`,
 * see the composer `suggest`).
 *
 * Bridges the framework port onto any Flysystem adapter — local, in-memory,
 * S3, FTP, SFTP — so cloud/abstract storage plugs in without touching caller
 * code. Root confinement is delegated to Flysystem's path normalisation
 * (`PathTraversalDetected` on `..` breakout), matching the port's contract.
 *
 * @api
 */
final readonly class FlysystemFilesystem implements FilesystemInterface
{
    public function __construct(
        private FilesystemOperator $flysystem,
    ) {}

    public function exists(string $path): bool
    {
        try {
            return $this->flysystem->fileExists($path);
        } catch (FilesystemException $filesystemException) {
            throw new MiddagInfrastructureException(sprintf('File "%s" could not be checked.', $path), $filesystemException->getCode(), previous: $filesystemException);
        }
    }

    public function read(string $path): string
    {
        try {
            if (!$this->flysystem->fileExists($path)) {
                throw new MiddagInfrastructureException(sprintf('File "%s" not found.', $path));
            }

            return $this->flysystem->read($path);
        } catch (FilesystemException $filesystemException) {
            throw new MiddagInfrastructureException(sprintf('File "%s" could not be read.', $path), $filesystemException->getCode(), previous: $filesystemException);
        }
    }

    public function write(string $path, string $contents): void
    {
        try {
            $this->flysystem->write($path, $contents);
        } catch (FilesystemException $filesystemException) {
            throw new MiddagInfrastructureException(sprintf('File "%s" could not be written.', $path), $filesystemException->getCode(), previous: $filesystemException);
        }
    }

    public function delete(string $path): void
    {
        try {
            // Flysystem's delete is already idempotent for missing files,
            // matching the port's contract.
            $this->flysystem->delete($path);
        } catch (FilesystemException $filesystemException) {
            throw new MiddagInfrastructureException(sprintf('File "%s" could not be deleted.', $path), $filesystemException->getCode(), previous: $filesystemException);
        }
    }
}
