<?php

declare(strict_types=1);

/**
 * middag-io/framework — MIDDAG PHP Framework.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Tests\Filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Filesystem\FlysystemFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(FlysystemFilesystem::class)]
final class FlysystemFilesystemTest extends TestCase
{
    private string $root;

    private FlysystemFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/middag-flysystem-test-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o775, true);

        $this->filesystem = new FlysystemFilesystem(
            new Filesystem(new LocalFilesystemAdapter($this->root)),
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testImplementsThePort(): void
    {
        self::assertInstanceOf(FilesystemInterface::class, $this->filesystem);
    }

    public function testWriteReadRoundTrip(): void
    {
        $this->filesystem->write('nested/dir/file.txt', 'payload');

        self::assertTrue($this->filesystem->exists('nested/dir/file.txt'));
        self::assertSame('payload', $this->filesystem->read('nested/dir/file.txt'));
    }

    public function testExistsIsFalseForMissingFile(): void
    {
        self::assertFalse($this->filesystem->exists('missing.txt'));
    }

    public function testReadMissingFileThrows(): void
    {
        $this->expectException(MiddagInfrastructureException::class);

        $this->filesystem->read('missing.txt');
    }

    public function testDeleteIsIdempotent(): void
    {
        $this->filesystem->write('a.txt', 'x');
        $this->filesystem->delete('a.txt');
        $this->filesystem->delete('a.txt');

        self::assertFalse($this->filesystem->exists('a.txt'));
    }

    public function testTraversalIsRejected(): void
    {
        $this->expectException(MiddagInfrastructureException::class);

        $this->filesystem->read('../outside.txt');
    }

    public function testExistsWrapsAFlysystemException(): void
    {
        $filesystem = new FlysystemFilesystem($this->throwingOperator('fileExists'));

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('could not be checked');
        $filesystem->exists('x.txt');
    }

    public function testWriteWrapsAFlysystemException(): void
    {
        $filesystem = new FlysystemFilesystem($this->throwingOperator('write'));

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('could not be written');
        $filesystem->write('x.txt', 'data');
    }

    public function testDeleteWrapsAFlysystemException(): void
    {
        $filesystem = new FlysystemFilesystem($this->throwingOperator('delete'));

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('could not be deleted');
        $filesystem->delete('x.txt');
    }

    /**
     * A Flysystem operator that throws a FilesystemException from $method,
     * exercising the port's error-wrapping catch blocks.
     */
    private function throwingOperator(string $method): FilesystemOperator
    {
        $failure = new class('flysystem failure') extends RuntimeException implements FilesystemException {};

        $operator = $this->createMock(FilesystemOperator::class);
        $operator->method($method)->willThrowException($failure);

        return $operator;
    }
}
